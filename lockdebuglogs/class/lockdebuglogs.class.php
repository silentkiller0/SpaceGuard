<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/class/lockdebuglogs.class.php
 * \ingroup lockdebuglogs
 * \brief   Service class for LockDebugLogs
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
dol_include_once('/lockdebuglogs/lib/lockdebuglogs.lib.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogslogger.class.php');

/**
 * LockDebugLogs service
 */
class LockDebugLogs
{
	/** @var bool One check per HTTP request (updateSession + other hooks). */
	private static $checkDoneThisRequest = false;

	/** @var array<int,array<string,string>>|null Module constants read in one query: entity => name => value. */
	private static $constCache = null;

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string */
	public $output = '';

	/** @var int Number of safeguards that really ran during the last runScheduledActions() */
	public $actionsDone = 0;

	/** Entity for module constants (same as syslog globals). */
	const CONST_ENTITY = 0;

	/** Safeguard blocks, keyed by constant prefix, value = lang key of the block title. */
	const BLOCKS = array(
		'DISABLE_MODULE' => 'LockDebugLogsSectionDisable',
		'DELETE_FILE' => 'LockDebugLogsSectionDelete',
	);

	/** Failed attempts after which a safeguard gives up and switches itself off. */
	const MAX_FAILS = 3;

	/** Seconds to wait before a failed safeguard is tried again. */
	const RETRY_DELAY = 300;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Load all LOCKDEBUGLOGS_* constants of entity 0 and the current entity with ONE query.
	 *
	 * @return void
	 */
	private function loadConstCache()
	{
		global $conf;

		if (self::$constCache !== null) {
			return;
		}
		self::$constCache = array();

		$entities = array((int) self::CONST_ENTITY);
		if (!empty($conf->entity)) {
			$entities[] = (int) $conf->entity;
		}

		$sql = "SELECT name, ".$this->db->sanitize($this->db->decrypt('value'))." as value, entity";
		$sql .= " FROM ".$this->db->prefix()."const";
		$sql .= " WHERE name LIKE 'LOCKDEBUGLOGS%'";
		$sql .= " AND entity IN (".$this->db->sanitize(implode(',', $entities)).")";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				self::$constCache[(int) $obj->entity][$obj->name] = (string) dolDecrypt($obj->value);
			}
		}
	}

	/**
	 * Read a module constant (stored on entity 0), with fallbacks for multicompany.
	 *
	 * @param string $name    Constant name
	 * @param string $default Default
	 * @return string
	 */
	public function getModuleConst($name, $default = '')
	{
		global $conf;

		$this->loadConstCache();

		$value = (isset(self::$constCache[self::CONST_ENTITY][$name]) ? self::$constCache[self::CONST_ENTITY][$name] : '');
		if ($value === '' && !empty($conf->entity) && isset(self::$constCache[(int) $conf->entity][$name])) {
			$value = self::$constCache[(int) $conf->entity][$name];
		}
		if ($value === '') {
			$value = getDolGlobalString($name);
		}

		return ($value !== '' ? $value : $default);
	}

	/**
	 * Drop the per-request constant cache (needed only after constants were changed outside this class).
	 *
	 * @return void
	 */
	public static function resetCache()
	{
		self::$constCache = null;
	}

	/**
	 * Write a module constant on entity 0 and drop the read cache.
	 *
	 * @param string $name  Constant name
	 * @param string $value Value
	 * @return int          >0 if OK
	 */
	private function setConst($name, $value)
	{
		self::$constCache = null;

		return dolibarr_set_const($this->db, $name, (string) $value, 'chaine', 0, '', self::CONST_ENTITY);
	}

	/**
	 * Delete a module constant on entity 0 and drop the read cache.
	 *
	 * @param string $name Constant name
	 * @return int         >0 if OK
	 */
	private function delConst($name)
	{
		self::$constCache = null;

		return dolibarr_del_const($this->db, $name, self::CONST_ENTITY);
	}

	/**
	 * Read a module constant as integer
	 *
	 * @param string $name    Constant name
	 * @param int    $default Default
	 * @return int
	 */
	public function getModuleConstInt($name, $default = 0)
	{
		return (int) $this->getModuleConst($name, (string) $default);
	}

	/**
	 * Snapshot of all LOCKDEBUGLOGS_* constants for diagnostics
	 *
	 * @return array<string,string|int>
	 */
	public function getDiagnosticsSnapshot()
	{
		global $conf, $user;

		$this->loadConstCache();

		$out = array(
			'conf_entity' => $conf->entity,
			'const_entity_used' => self::CONST_ENTITY,
			'lockdebuglogs_enabled' => isModEnabled('lockdebuglogs') ? 1 : 0,
			'syslog_enabled' => isModEnabled('syslog') ? 1 : 0,
			'dol_now' => dol_now(),
			'dol_now_human' => dol_print_date(dol_now(), 'standard'),
			'dol_now_user_tz' => dol_print_date(dol_now(), 'standard', 'tzuser'),
			'php_timezone' => @date_default_timezone_get(),
			'datetime_parse_mode' => $this->getDateTimeParseMode(),
			'user_id' => (is_object($user) ? $user->id : 0),
			'php_sapi' => PHP_SAPI,
			'script' => empty($_SERVER['SCRIPT_NAME']) ? '' : $_SERVER['SCRIPT_NAME'],
		);

		foreach (lockdebuglogsGetConstNames() as $cst) {
			$out['db_e0_'.$cst] = (isset(self::$constCache[self::CONST_ENTITY][$cst]) ? self::$constCache[self::CONST_ENTITY][$cst] : '');
			if (!empty($conf->entity)) {
				$out['db_e'.$conf->entity.'_'.$cst] = (isset(self::$constCache[(int) $conf->entity][$cst]) ? self::$constCache[(int) $conf->entity][$cst] : '');
			}
			$out['conf_global_'.$cst] = getDolGlobalString($cst);
			$out['resolved_'.$cst] = $this->getModuleConst($cst, '');
		}

		$out['MAIN_MODULE_SYSLOG_db_e0'] = dolibarr_get_const($this->db, 'MAIN_MODULE_SYSLOG', self::CONST_ENTITY);
		$out['MAIN_MODULE_SYSLOG_conf'] = getDolGlobalString('MAIN_MODULE_SYSLOG');

		return $out;
	}

	/**
	 * Resolve main syslog file path from configuration
	 *
	 * @return string Empty if not configured
	 */
	public function getLogFilePath()
	{
		if (!isModEnabled('syslog')) {
			return '';
		}

		return $this->getLogFilePathFromConst();
	}

	/**
	 * Resolve syslog file path from SYSLOG_FILE constant (even if syslog module is off)
	 *
	 * @return string
	 */
	public function getLogFilePathFromConst()
	{
		$filelog = dolibarr_get_const($this->db, 'SYSLOG_FILE', self::CONST_ENTITY);
		if ($filelog === '') {
			$filelog = 'dolibarr.log';
		}
		$filelog = preg_replace('/DOL_DATA_ROOT/i', DOL_DATA_ROOT, $filelog);

		return $filelog;
	}

	/**
	 * Ensure log path is under DOL_DATA_ROOT (anti-tampering)
	 *
	 * @param string $path Full path
	 * @return bool
	 */
	public function isLogFilePathSafe($path)
	{
		if ($path === '') {
			return false;
		}

		$realroot = realpath(DOL_DATA_ROOT);
		if ($realroot === false) {
			return false;
		}
		$realroot = rtrim(str_replace('\\', '/', $realroot), '/').'/';

		if (dol_is_file($path)) {
			$realfile = realpath($path);
		} else {
			$dir = realpath(dirname($path));
			if ($dir === false) {
				return false;
			}
			$realfile = rtrim(str_replace('\\', '/', $dir), '/').'/'.basename($path);
		}

		if ($realfile === false) {
			return false;
		}

		$realfile = str_replace('\\', '/', $realfile);

		return (strncmp($realfile, $realroot, strlen($realroot)) === 0);
	}

	/**
	 * How datetime form values are converted to timestamps (matches browser datetime-local + Dolibarr TZ).
	 *
	 * @return string tzuser|tzserver
	 */
	public function getDateTimeParseMode()
	{
		global $user;

		if (is_object($user) && !empty($user->id)) {
			return 'tzuser';
		}

		return 'tzserver';
	}

	/**
	 * Compute deadline timestamp from mode
	 *
	 * @param string $mode       duration|datetime
	 * @param int    $duration   Seconds when mode=duration
	 * @param string $datetime   dol_print_date compatible string when mode=datetime
	 * @return int               <=0 if invalid
	 */
	public function computeDeadlineTimestamp($mode, $duration, $datetime)
	{
		if ($mode === 'duration') {
			if ($duration <= 0) {
				return 0;
			}
			return dol_now() + $duration;
		}

		if ($mode === 'datetime') {
			$datetime = trim($datetime);
			if ($datetime === '') {
				return 0;
			}
			$datetime = str_replace('T', ' ', $datetime);
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
				$datetime .= ':00';
			}
			// datetime-local is wall-clock time: use user TZ (setup) or server TZ (cron), not GMT.
			$parsemode = $this->getDateTimeParseMode();
			$ts = dol_stringtotime($datetime, $parsemode);
			LockDebugLogsLogger::log('computeDeadlineTimestamp', LOG_DEBUG, array(
				'input' => $datetime,
				'parse_mode' => $parsemode,
				'timestamp' => $ts,
				'timestamp_human' => ($ts > 0 ? dol_print_date($ts, 'standard') : ''),
			));
			if (empty($ts)) {
				return 0;
			}
			return (int) $ts;
		}

		return 0;
	}

	/**
	 * Save block configuration
	 *
	 * @param string $prefix   DISABLE_MODULE or DELETE_FILE (without LOCKDEBUGLOGS_)
	 * @param int    $enabled  0|1
	 * @param string $mode     duration|datetime
	 * @param int    $duration Seconds
	 * @param string $datetime User datetime string
	 * @return int             <0 if error
	 */
	public function saveBlockConfig($prefix, $enabled, $mode, $duration, $datetime)
	{
		$enabled = $enabled ? 1 : 0;
		if (!in_array($mode, array('duration', 'datetime'), true)) {
			$mode = 'duration';
		}

		LockDebugLogsLogger::log('saveBlockConfig start', LOG_INFO, array(
			'prefix' => $prefix,
			'enabled' => $enabled,
			'mode' => $mode,
			'duration' => $duration,
			'datetime' => $datetime,
		));

		// A fresh save starts with a clean retry state.
		$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_FAILS');
		$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_RETRY_AT');

		$res = $this->setConst('LOCKDEBUGLOGS_'.$prefix.'_ENABLED', $enabled);
		if ($res <= 0) {
			LockDebugLogsLogger::log('saveBlockConfig failed set ENABLED', LOG_ERR, array('prefix' => $prefix, 'res' => $res));
			return -1;
		}

		$res = $this->setConst('LOCKDEBUGLOGS_'.$prefix.'_MODE', $mode);
		if ($res <= 0) {
			LockDebugLogsLogger::log('saveBlockConfig failed set MODE', LOG_ERR, array('prefix' => $prefix, 'res' => $res));
			return -1;
		}

		if ($duration <= 0) {
			$duration = 1800;
		}
		$this->setConst('LOCKDEBUGLOGS_'.$prefix.'_DURATION', $duration);

		$datetime = trim(str_replace('T', ' ', (string) $datetime));
		if ($datetime !== '') {
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
				$datetime .= ':00';
			}
			$this->setConst('LOCKDEBUGLOGS_'.$prefix.'_DATETIME', $datetime);
		}

		if (!$enabled) {
			$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_AT');
			LockDebugLogsLogger::log('saveBlockConfig form saved (safeguard off)', LOG_INFO, array('prefix' => $prefix));
			return 1;
		}

		$deadline = $this->computeDeadlineTimestamp($mode, $duration, $datetime);
		if ($deadline <= 0 || $deadline <= dol_now()) {
			$this->error = 'InvalidDeadline';
			LockDebugLogsLogger::log('saveBlockConfig invalid deadline', LOG_WARNING, array(
				'prefix' => $prefix,
				'deadline' => $deadline,
				'dol_now' => dol_now(),
			));
			return -2;
		}

		$res = $this->setConst('LOCKDEBUGLOGS_'.$prefix.'_AT', $deadline);
		if ($res <= 0) {
			LockDebugLogsLogger::log('saveBlockConfig failed set AT', LOG_ERR, array('prefix' => $prefix, 'res' => $res));
			return -1;
		}

		LockDebugLogsLogger::log('saveBlockConfig OK', LOG_INFO, array(
			'prefix' => $prefix,
			'deadline_ts' => $deadline,
			'deadline_human' => dol_print_date($deadline, 'standard'),
		));

		return 1;
	}

	/**
	 * Clear scheduled constants for one block (keep ENABLED=0 already set)
	 *
	 * @param string $prefix DISABLE_MODULE|DELETE_FILE
	 * @return void
	 */
	public function clearBlockConfig($prefix)
	{
		foreach (array('MODE', 'DURATION', 'DATETIME', 'AT', 'FAILS', 'RETRY_AT') as $suffix) {
			$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_'.$suffix);
		}
	}

	/**
	 * After a safeguard ran (or gave up): switch it off and clear its deadline and retry state,
	 * but keep the form values (mode, duration, datetime).
	 *
	 * @param string $prefix DISABLE_MODULE|DELETE_FILE
	 * @return void
	 */
	public function finishBlock($prefix)
	{
		$this->setConst('LOCKDEBUGLOGS_'.$prefix.'_ENABLED', '0');
		$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_AT');
		$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_FAILS');
		$this->delConst('LOCKDEBUGLOGS_'.$prefix.'_RETRY_AT');
	}

	/**
	 * Delete all module constants
	 *
	 * @return void
	 */
	public function deleteAllConstants()
	{
		foreach (lockdebuglogsGetConstNames() as $name) {
			$this->delConst($name);
		}
	}

	/**
	 * Is the given safeguard enabled, past its deadline and not waiting for a retry?
	 *
	 * @param string $prefix DISABLE_MODULE|DELETE_FILE
	 * @param int    $now    Timestamp
	 * @return bool
	 */
	private function isBlockDue($prefix, $now)
	{
		$enabled = $this->getModuleConstInt('LOCKDEBUGLOGS_'.$prefix.'_ENABLED');
		$at = $this->getModuleConstInt('LOCKDEBUGLOGS_'.$prefix.'_AT');
		$retryAt = $this->getModuleConstInt('LOCKDEBUGLOGS_'.$prefix.'_RETRY_AT');

		return ($enabled && $at > 0 && $now >= $at && $now >= $retryAt);
	}

	/**
	 * Cheap check used on every page load: is any safeguard due right now?
	 * Costs one query (constants are cached for the request).
	 *
	 * @param int $now Timestamp (0 = now)
	 * @return bool
	 */
	public function isAnythingDue($now = 0)
	{
		if (empty($now)) {
			$now = dol_now();
		}
		foreach (array_keys(self::BLOCKS) as $prefix) {
			if ($this->isBlockDue($prefix, $now)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Run safeguards without UI messages (background / any page load / cron).
	 * Does nothing, and writes nothing to the log, while no deadline is due.
	 *
	 * @param DoliDB $db      Database
	 * @param string $trigger Source label for logs
	 * @return int
	 */
	public static function runSilent($db, $trigger = 'unknown')
	{
		if (self::$checkDoneThisRequest) {
			return 0;
		}
		self::$checkDoneThisRequest = true;

		$service = new LockDebugLogs($db);
		if (!$service->isAnythingDue()) {
			return 0;
		}

		LockDebugLogsLogger::log('runSilent called', LOG_INFO, array('trigger' => $trigger));
		$result = $service->runScheduledActions($trigger);
		LockDebugLogsLogger::log('runSilent finished', LOG_INFO, array(
			'trigger' => $trigger,
			'result' => $result,
			'output' => $service->output,
			'error' => $service->error,
		));

		return $result;
	}

	/**
	 * Run safeguards and show the outcome as Dolibarr event messages (manual "Execute it now").
	 *
	 * @param DoliDB $db      Database
	 * @param string $trigger Source label for logs
	 * @return int
	 */
	public static function runAndNotify($db, $trigger = 'unknown')
	{
		global $langs;

		// An explicit request: do not skip it because the page-load hook already ran this request.
		self::$checkDoneThisRequest = true;

		LockDebugLogsLogger::log('runAndNotify called', LOG_INFO, array('trigger' => $trigger));

		$service = new LockDebugLogs($db);
		$result = $service->runScheduledActions($trigger);

		$langs->load('lockdebuglogs@lockdebuglogs');
		if ($result < 0 && !empty($service->error)) {
			setEventMessages($langs->trans($service->error), null, 'errors');
		} elseif ($service->actionsDone > 0) {
			setEventMessages($service->output, null, 'mesgs');
		} else {
			setEventMessages($langs->trans('LockDebugLogsNoActionDue'), null, 'mesgs');
		}

		LockDebugLogsLogger::log('runAndNotify finished', LOG_INFO, array(
			'trigger' => $trigger,
			'result' => $result,
			'output' => $service->output,
			'error' => $service->error,
		));

		return $result;
	}

	/**
	 * Take the run lock so that two requests (or a request and cron) never execute the same action.
	 *
	 * @return resource|false|null Handle when locked, false when another run holds the lock, null when locking is unavailable
	 */
	private function acquireLock()
	{
		$dir = DOL_DATA_ROOT.'/admin/temp';
		if (!is_dir($dir)) {
			dol_mkdir($dir);
		}

		$handle = @fopen($dir.'/lockdebuglogs.lock', 'c');
		if ($handle === false) {
			LockDebugLogsLogger::log('cannot open lock file, running without lock', LOG_WARNING, array('dir' => $dir));
			return null;
		}
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return false;
		}

		return $handle;
	}

	/**
	 * Release the run lock
	 *
	 * @param resource|false|null $handle Value returned by acquireLock()
	 * @return void
	 */
	private function releaseLock($handle)
	{
		if (is_resource($handle)) {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/**
	 * Record a failed attempt of a safeguard: retry later, or give up (switch off) after MAX_FAILS
	 * or at once when retrying cannot help.
	 *
	 * @param string   $prefix    DISABLE_MODULE|DELETE_FILE
	 * @param string   $error     Lang key or message describing the failure
	 * @param int      $now       Timestamp
	 * @param string[] $messages  Collects the lines for the "last action" note
	 * @param bool     $permanent True if a retry is pointless
	 * @return int                Always -1
	 */
	private function registerFailure($prefix, $error, $now, array &$messages, $permanent = false)
	{
		global $langs;

		$this->error = $error;
		$label = $langs->trans(self::BLOCKS[$prefix]);
		$fails = $this->getModuleConstInt('LOCKDEBUGLOGS_'.$prefix.'_FAILS') + 1;

		if ($permanent || $fails >= self::MAX_FAILS) {
			$this->finishBlock($prefix);
			$messages[] = $langs->trans('LockDebugLogsActionGaveUp', $label, $fails, $langs->trans($error));
			LockDebugLogsLogger::log('safeguard gave up', LOG_ERR, array('prefix' => $prefix, 'fails' => $fails, 'error' => $error));

			return -1;
		}

		$this->setConst('LOCKDEBUGLOGS_'.$prefix.'_FAILS', $fails);
		$this->setConst('LOCKDEBUGLOGS_'.$prefix.'_RETRY_AT', $now + self::RETRY_DELAY);
		$messages[] = $langs->trans('LockDebugLogsActionFailed', $label, $fails, self::MAX_FAILS, $langs->trans($error));
		LockDebugLogsLogger::log('safeguard failed, will retry', LOG_WARNING, array(
			'prefix' => $prefix,
			'fails' => $fails,
			'retry_at' => $now + self::RETRY_DELAY,
			'error' => $error,
		));

		return -1;
	}

	/**
	 * Safeguard A: disable the native Debug Logs module
	 *
	 * @param int      $now      Timestamp
	 * @param string[] $messages Collects the lines for the "last action" note
	 * @return int               <0 on failure
	 */
	private function runDisableBlock($now, array &$messages)
	{
		global $langs;

		LockDebugLogsLogger::log('disable block: deadline reached, executing', LOG_INFO);

		if (isModEnabled('syslog')) {
			$err = unActivateModule('modSyslog', 0);
			LockDebugLogsLogger::log('unActivateModule(modSyslog) returned', LOG_INFO, array(
				'error_string' => $err,
				'MAIN_MODULE_SYSLOG_entity0' => dolibarr_get_const($this->db, 'MAIN_MODULE_SYSLOG', self::CONST_ENTITY),
				'syslog_enabled_after' => isModEnabled('syslog') ? 1 : 0,
			));
			if ($err !== '') {
				return $this->registerFailure('DISABLE_MODULE', $err, $now, $messages);
			}
			$messages[] = $langs->trans('LockDebugLogsActionDisabledSyslog', dol_print_date($now, 'dayhour'));
		} else {
			LockDebugLogsLogger::log('disable block: syslog already off', LOG_INFO);
			$messages[] = $langs->trans('LockDebugLogsActionSyslogAlreadyOff');
		}

		$this->finishBlock('DISABLE_MODULE');
		$this->actionsDone++;

		return 0;
	}

	/**
	 * Safeguard B: delete the syslog file
	 *
	 * @param int      $now      Timestamp
	 * @param string[] $messages Collects the lines for the "last action" note
	 * @return int               <0 on failure
	 */
	private function runDeleteBlock($now, array &$messages)
	{
		global $langs;

		LockDebugLogsLogger::log('delete block: deadline reached, executing', LOG_INFO);

		$path = $this->getLogFilePathFromConst();
		$exists = ($path !== '' && dol_is_file($path));
		$safe = ($path !== '' && $this->isLogFilePathSafe($path));
		LockDebugLogsLogger::log('delete block path', LOG_INFO, array(
			'path' => $path,
			'safe' => $safe ? 1 : 0,
			'exists' => $exists ? 1 : 0,
		));

		if (!$exists) {
			$messages[] = $langs->trans('LockDebugLogsActionLogNotFound');
		} elseif (!$safe) {
			// Retrying cannot make an unsafe path safe: give up at once.
			return $this->registerFailure('DELETE_FILE', 'LockDebugLogsErrorUnsafeLogPath', $now, $messages, true);
		} else {
			$result = dol_delete_file($path, 0, 0, 0, null, false, 0);
			LockDebugLogsLogger::log('dol_delete_file result', LOG_INFO, array('result' => $result));
			if (!$result) {
				return $this->registerFailure('DELETE_FILE', 'LockDebugLogsErrorDeleteLog', $now, $messages);
			}
			$messages[] = $langs->trans('LockDebugLogsActionDeletedLog', $path);
		}

		$this->finishBlock('DELETE_FILE');
		$this->actionsDone++;

		return 0;
	}

	/**
	 * Cron entry: run due actions
	 *
	 * @param string $trigger Source label for logs
	 * @return int            0 if OK (also when nothing was due), <0 if a safeguard failed
	 */
	public function runScheduledActions($trigger = 'internal')
	{
		global $langs;

		$this->error = '';
		$this->output = '';
		$this->actionsDone = 0;
		$langs->load('lockdebuglogs@lockdebuglogs');

		if (!isModEnabled('lockdebuglogs')) {
			$this->output = $langs->trans('LockDebugLogsModuleOff');
			LockDebugLogsLogger::log('abort: lockdebuglogs module not enabled in conf->modules', LOG_WARNING);
			return 0;
		}

		$lock = $this->acquireLock();
		if ($lock === false) {
			$this->output = $langs->trans('LockDebugLogsNoActionDue');
			LockDebugLogsLogger::log('another run holds the lock, skipping', LOG_INFO, array('trigger' => $trigger));
			return 0;
		}

		try {
			// Re-read after taking the lock: a run that just finished may have changed the constants.
			self::$constCache = null;
			$now = dol_now();

			if (!$this->isAnythingDue($now)) {
				$this->output = $langs->trans('LockDebugLogsNoActionDue');
				LockDebugLogsLogger::log('no action due', LOG_DEBUG, array('trigger' => $trigger));
				return 0;
			}

			LockDebugLogsLogger::log('runScheduledActions start', LOG_INFO, array('trigger' => $trigger));
			LockDebugLogsLogger::log('diagnostics snapshot', LOG_DEBUG, ((int) getDolGlobalInt('SYSLOG_LEVEL') >= LOG_DEBUG ? $this->getDiagnosticsSnapshot() : array()));

			$messages = array();
			$failed = 0;

			// A. Disable the syslog module first, B. then delete the log file.
			if ($this->isBlockDue('DISABLE_MODULE', $now) && $this->runDisableBlock($now, $messages) < 0) {
				$failed++;
			}
			if ($this->isBlockDue('DELETE_FILE', $now) && $this->runDeleteBlock($now, $messages) < 0) {
				$failed++;
			}

			if (!empty($messages)) {
				$note = implode(' | ', $messages);
				$this->setConst('LOCKDEBUGLOGS_LAST_ACTION_NOTE', $note);
				$this->output = $note;
				LockDebugLogsLogger::log('actions performed', LOG_INFO, array('messages' => $messages));
			} else {
				$this->output = $langs->trans('LockDebugLogsNoActionDue');
			}

			LockDebugLogsLogger::log('runScheduledActions end', LOG_INFO, array('output' => $this->output, 'error' => $this->error));

			return ($failed > 0 ? -1 : 0);
		} finally {
			$this->releaseLock($lock);
		}
	}
}
