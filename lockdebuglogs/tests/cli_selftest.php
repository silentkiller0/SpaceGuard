<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/tests/cli_selftest.php
 * \ingroup lockdebuglogs
 * \brief   CLI self-test of the safeguards. Run as the web server user:
 *          sudo -u www-data php lockdebuglogs/tests/cli_selftest.php
 *          It temporarily points SYSLOG_FILE at a test file and restores every changed constant at the end.
 *          It never disables the Debug Logs module while that module is enabled.
 */

if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}

$dir = __DIR__;
for ($i = 0; $i < 6 && !defined('DOL_DOCUMENT_ROOT'); $i++) {
	$dir = dirname($dir);
	if (file_exists($dir.'/master.inc.php')) {
		require $dir.'/master.inc.php';
	}
}
if (!defined('DOL_DOCUMENT_ROOT')) {
	die("master.inc.php not found\n");
}
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
	die("Run as the web server user (sudo -u www-data php ...): files created by root break document generation.\n");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lockdebuglogs/lib/lockdebuglogs.lib.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 */
$langs->setDefaultLang('en_US');
$langs->load('lockdebuglogs@lockdebuglogs');

$pass = 0;
$fail = 0;
$check = function ($label, $ok, $detail = '') use (&$pass, &$fail) {
	if ($ok) {
		$pass++;
		echo "  PASS  $label\n";
	} else {
		$fail++;
		echo "  FAIL  $label $detail\n";
	}
};

$set = function ($name, $value) use ($db) {
	dolibarr_set_const($db, $name, (string) $value, 'chaine', 0, '', 0);
	LockDebugLogs::resetCache();
};
$get = function ($name) use ($db) {
	return dolibarr_get_const($db, $name, 0);
};
$questions = function () use ($db) {
	$res = $db->query("SHOW SESSION STATUS LIKE 'Questions'");
	$obj = $db->fetch_row($res);

	return (int) $obj[1];
};

// Snapshot of everything the test touches (entity 0 only).
$names = array_merge(lockdebuglogsGetConstNames(), array('SYSLOG_FILE'));
$before = array();
foreach ($names as $name) {
	$before[$name] = $get($name);
}
$restore = function () use ($names, &$before, $db) {
	foreach ($names as $name) {
		if ($before[$name] === '') {
			dolibarr_del_const($db, $name, 0);
		} else {
			dolibarr_set_const($db, $name, $before[$name], 'chaine', 0, '', 0);
		}
	}
	LockDebugLogs::resetCache();
};
$clearAll = function () use ($names, $db) {
	foreach (lockdebuglogsGetConstNames() as $name) {
		dolibarr_del_const($db, $name, 0);
	}
	LockDebugLogs::resetCache();
};

$testfile = DOL_DATA_ROOT.'/lockdebuglogs_selftest.log';
$outside = sys_get_temp_dir().'/lockdebuglogs_selftest_outside.log';
$past = dol_now() - 60;

try {
	$set('SYSLOG_FILE', $testfile);

	echo "T1 idle page load costs one query and logs nothing\n";
	$clearAll();
	$service = new LockDebugLogs($db);
	$q0 = $questions();
	$due = $service->isAnythingDue();
	$q1 = $questions();
	$check('nothing due while all safeguards are off', $due === false);
	$check('one constants query (got '.($q1 - $q0 - 1).')', ($q1 - $q0 - 1) <= 1);

	echo "T2 delete safeguard, happy path\n";
	file_put_contents($testfile, "x\n");
	$service = new LockDebugLogs($db);
	$check('save accepted', $service->saveBlockConfig('DELETE_FILE', 1, 'duration', 300, '') === 1);
	$check('not due right after saving', (new LockDebugLogs($db))->isAnythingDue() === false);
	$set('LOCKDEBUGLOGS_DELETE_FILE_AT', $past);
	$service = new LockDebugLogs($db);
	$res = $service->runScheduledActions('selftest');
	$check('run returns 0', $res === 0, "(got $res)");
	$check('file deleted', !file_exists($testfile));
	$check('actionsDone = 1', $service->actionsDone === 1);
	$check('safeguard switched off, deadline cleared', $get('LOCKDEBUGLOGS_DELETE_FILE_ENABLED') === '0' && $get('LOCKDEBUGLOGS_DELETE_FILE_AT') === '');
	$check('last action note written', strpos($get('LOCKDEBUGLOGS_LAST_ACTION_NOTE'), 'deleted') !== false || strpos($get('LOCKDEBUGLOGS_LAST_ACTION_NOTE'), $testfile) !== false);
	$check('form values kept', $get('LOCKDEBUGLOGS_DELETE_FILE_MODE') === 'duration' && $get('LOCKDEBUGLOGS_DELETE_FILE_DURATION') === '300');

	echo "T3 unsafe path: gives up at once, file untouched\n";
	file_put_contents($outside, "x\n");
	$set('SYSLOG_FILE', $outside);
	$service = new LockDebugLogs($db);
	$service->saveBlockConfig('DELETE_FILE', 1, 'duration', 300, '');
	$set('LOCKDEBUGLOGS_DELETE_FILE_AT', $past);
	$service = new LockDebugLogs($db);
	$res = $service->runScheduledActions('selftest');
	$check('run returns -1', $res === -1);
	$check('error key is unsafe path', $service->error === 'LockDebugLogsErrorUnsafeLogPath');
	$check('file untouched', file_exists($outside));
	$check('switched off immediately (no retry loop)', $get('LOCKDEBUGLOGS_DELETE_FILE_ENABLED') === '0');
	$check('note says it gave up', stripos($get('LOCKDEBUGLOGS_LAST_ACTION_NOTE'), 'gave up') !== false);
	$res = (new LockDebugLogs($db))->runScheduledActions('selftest');
	$check('next run does nothing', $res === 0);

	echo "T4 failure handling: 3 attempts, 5 minutes apart, then give up\n";
	// dol_delete_file() repairs a read-only parent directory by itself, so a real delete failure cannot be
	// produced portably; the retry logic is exercised directly.
	$service = new LockDebugLogs($db);
	$service->saveBlockConfig('DELETE_FILE', 1, 'duration', 300, '');
	$set('LOCKDEBUGLOGS_DELETE_FILE_AT', $past);
	$failMethod = new ReflectionMethod('LockDebugLogs', 'registerFailure');
	$failMethod->setAccessible(true);
	$messages = array();
	$call = function () use ($failMethod, $service, &$messages) {
		return $failMethod->invokeArgs($service, array('DELETE_FILE', 'LockDebugLogsErrorDeleteLog', dol_now(), &$messages));
	};
	$check('attempt 1 returns -1', $call() === -1);
	$retryAt = (int) $get('LOCKDEBUGLOGS_DELETE_FILE_RETRY_AT');
	$check('still enabled, FAILS=1, retry in ~300 s', $get('LOCKDEBUGLOGS_DELETE_FILE_ENABLED') === '1' && $get('LOCKDEBUGLOGS_DELETE_FILE_FAILS') === '1' && $retryAt > dol_now() + 250);
	$res = (new LockDebugLogs($db))->runScheduledActions('selftest');
	$check('page load / cron during the wait does nothing', $res === 0 && $get('LOCKDEBUGLOGS_DELETE_FILE_FAILS') === '1');
	$set('LOCKDEBUGLOGS_DELETE_FILE_RETRY_AT', $past);
	$check('due again once the wait is over', (new LockDebugLogs($db))->isAnythingDue() === true);
	LockDebugLogs::resetCache();
	$service = new LockDebugLogs($db);
	$check('attempt 2 returns -1, FAILS=2', $call() === -1 && $get('LOCKDEBUGLOGS_DELETE_FILE_FAILS') === '2');
	$check('attempt 3 gives up (-1)', $call() === -1);
	$check('switched off, retry state cleared', $get('LOCKDEBUGLOGS_DELETE_FILE_ENABLED') === '0' && $get('LOCKDEBUGLOGS_DELETE_FILE_FAILS') === '' && $get('LOCKDEBUGLOGS_DELETE_FILE_RETRY_AT') === '' && $get('LOCKDEBUGLOGS_DELETE_FILE_AT') === '');
	$check('gave-up message collected', stripos(end($messages), 'gave up') !== false);

	echo "T5 saving resets the retry state\n";
	$set('LOCKDEBUGLOGS_DELETE_FILE_FAILS', 2);
	$set('LOCKDEBUGLOGS_DELETE_FILE_RETRY_AT', dol_now() + 999);
	(new LockDebugLogs($db))->saveBlockConfig('DELETE_FILE', 0, 'duration', 300, '');
	$check('FAILS and RETRY_AT removed', $get('LOCKDEBUGLOGS_DELETE_FILE_FAILS') === '' && $get('LOCKDEBUGLOGS_DELETE_FILE_RETRY_AT') === '');

	echo "T6 a held run lock prevents a second execution\n";
	$set('SYSLOG_FILE', $testfile);
	file_put_contents($testfile, "x\n");
	(new LockDebugLogs($db))->saveBlockConfig('DELETE_FILE', 1, 'duration', 300, '');
	$set('LOCKDEBUGLOGS_DELETE_FILE_AT', $past);
	$lockfile = DOL_DATA_ROOT.'/admin/temp/lockdebuglogs.lock';
	$holder = fopen($lockfile, 'c');
	flock($holder, LOCK_EX | LOCK_NB);
	$service = new LockDebugLogs($db);
	$res = $service->runScheduledActions('selftest');
	$check('run skipped while lock is held', $res === 0 && $service->actionsDone === 0 && file_exists($testfile));
	flock($holder, LOCK_UN);
	fclose($holder);
	$service = new LockDebugLogs($db);
	$res = $service->runScheduledActions('selftest');
	$check('runs after the lock is released', $res === 0 && $service->actionsDone === 1 && !file_exists($testfile));

	echo "T7 safeguards are independent\n";
	if (isModEnabled('syslog')) {
		echo "  SKIP  Debug Logs module is enabled; the disable safeguard is not exercised\n";
	} else {
		file_put_contents($outside, "x\n");
		$set('SYSLOG_FILE', $outside);
		$s = new LockDebugLogs($db);
		$s->saveBlockConfig('DISABLE_MODULE', 1, 'duration', 300, '');
		$s->saveBlockConfig('DELETE_FILE', 1, 'duration', 300, '');
		$set('LOCKDEBUGLOGS_DISABLE_MODULE_AT', $past);
		$set('LOCKDEBUGLOGS_DELETE_FILE_AT', $past);
		$service = new LockDebugLogs($db);
		$service->runScheduledActions('selftest');
		$check('disable block ran although the delete block failed', $get('LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED') === '0' && $service->actionsDone === 1);
	}
} finally {
	@unlink($testfile);
	@unlink($outside);
	$restore();
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
