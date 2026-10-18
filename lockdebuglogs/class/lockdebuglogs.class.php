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
dol_include_once('/lockdebuglogs/lib/lockdebuglogs.lib.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogslogger.class.php');

/**
 * LockDebugLogs service
 */
class LockDebugLogs
{
	/** @var bool One check per HTTP request (updateSession + other hooks). */
	private static $checkDoneThisRequest = false;

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string */
	public $output = '';

	/** Entity for module constants (same as syslog globals). */
	const CONST_ENTITY = 0;

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
	 * Read a module constant (stored on entity 0), with fallbacks for multicompany.
	 *
	 * @param string $name    Constant name
	 * @param string $default Default
	 * @return string
	 */
	public function getModuleConst($name, $default = '')
	{
		global $conf;

		$value = dolibarr_get_const($this->db, $name, self::CONST_ENTITY);
		if ($value === '' && !empty($conf->entity)) {
			$value = dolibarr_get_const($this->db, $name, $conf->entity);
		}
		if ($value === '' && isset($conf->global->$name)) {
			$value = (string) $conf->global->$name;
		}

		return ($value !== '' ? $value : $default);
	}

	/**
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
		global $conf;

		global $user;

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
			$out['db_e0_'.$cst] = dolibarr_get_const($this->db, $cst, self::CONST_ENTITY);
			if (!empty($conf->entity)) {
				$out['db_e'.$conf->entity.'_'.$cst] = dolibarr_get_const($this->db, $cst, $conf->entity);
			}
			$out['conf_global_'.$cst] = isset($conf->global->$cst) ? (string) $conf->global->$cst : '';
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

		$res = dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_ENABLED', (string) $enabled, 'chaine', 0, '', self::CONST_ENTITY);
		if ($res <= 0) {
			LockDebugLogsLogger::log('saveBlockConfig failed set ENABLED', LOG_ERR, array('prefix' => $prefix, 'res' => $res));
			return -1;
		}

		$res = dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_MODE', $mode, 'chaine', 0, '', self::CONST_ENTITY);
		if ($res <= 0) {
			LockDebugLogsLogger::log('saveBlockConfig failed set MODE', LOG_ERR, array('prefix' => $prefix, 'res' => $res));
			return -1;
		}

		if ($duration <= 0) {
			$duration = 1800;
		}
		dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_DURATION', (string) $duration, 'chaine', 0, '', self::CONST_ENTITY);

		$datetime = trim(str_replace('T', ' ', (string) $datetime));
		if ($datetime !== '') {
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
				$datetime .= ':00';
			}
			dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_DATETIME', $datetime, 'chaine', 0, '', self::CONST_ENTITY);
		}

		if (!$enabled) {
			dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_AT', self::CONST_ENTITY);
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

		$res = dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_AT', (string) $deadline, 'chaine', 0, '', self::CONST_ENTITY);
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
		dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_MODE', self::CONST_ENTITY);
		dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_DURATION', self::CONST_ENTITY);
		dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_DATETIME', self::CONST_ENTITY);
		dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_AT', self::CONST_ENTITY);
	}

	/**
	 * After a safeguard ran: turn off scheduling but keep form values (mode, duration, datetime).
	 *
	 * @param string $prefix DISABLE_MODULE|DELETE_FILE
	 * @return void
	 */
	public function clearBlockDeadlineOnly($prefix)
	{
		dolibarr_del_const($this->db, 'LOCKDEBUGLOGS_'.$prefix.'_AT', self::CONST_ENTITY);
	}

	/**
	 * Delete all module constants
	 *
	 * @return void
	 */
	public function deleteAllConstants()
	{
		foreach (lockdebuglogsGetConstNames() as $name) {
			dolibarr_del_const($this->db, $name, self::CONST_ENTITY);
		}
	}

	/**
	 * Run scheduled actions and show Dolibarr event messages when something happened.
	 *
	 * @param DoliDB $db Database handler
	 * @return int
	 */
	/**
	 * Run safeguards without UI messages (background / any page load / cron).
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

		LockDebugLogsLogger::log('runSilent called', LOG_INFO, array('trigger' => $trigger));

		$service = new LockDebugLogs($db);
		$result = $service->runScheduledActions($trigger);

		LockDebugLogsLogger::log('runSilent finished', LOG_INFO, array(
			'trigger' => $trigger,
			'result' => $result,
			'output' => $service->output,
			'error' => $service->error,
		));

		return $result;
	}

	public static function runAndNotify($db, $trigger = 'unknown')
	{
		global $langs;

		LockDebugLogsLogger::log('runAndNotify called', LOG_INFO, array('trigger' => $trigger));

		if (self::$checkDoneThisRequest) {
			return 0;
		}
		self::$checkDoneThisRequest = true;

		$service = new LockDebugLogs($db);
		$result = $service->runScheduledActions($trigger);

		if ($result < 0 && !empty($service->error)) {
			setEventMessages($service->error, null, 'errors');
		} elseif (!empty($service->output) && $service->output !== 'No action due') {
			$langs->load('lockdebuglogs@lockdebuglogs');
			setEventMessages($service->output, null, 'mesgs');
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
	 * Cron entry: run due actions
	 *
	 * @return int 0 if OK for cron
	 */
	public function runScheduledActions($trigger = 'internal')
	{
		global $langs;

		LockDebugLogsLogger::log('runScheduledActions start', LOG_INFO, array('trigger' => $trigger));
		LockDebugLogsLogger::log('diagnostics snapshot', LOG_DEBUG, $this->getDiagnosticsSnapshot());

		if (!isModEnabled('lockdebuglogs')) {
			$this->output = 'Module LockDebugLogs disabled';
			LockDebugLogsLogger::log('abort: lockdebuglogs module not enabled in conf->modules', LOG_WARNING);
			return 0;
		}

		$langs->load('lockdebuglogs@lockdebuglogs');
		$now = dol_now();
		$messages = array();

		$disableEnabled = $this->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED');
		$disableAt = $this->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_AT');
		$deleteEnabled = $this->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_ENABLED');
		$deleteAt = $this->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_AT');

		LockDebugLogsLogger::log('deadline evaluation', LOG_INFO, array(
			'now' => $now,
			'now_human' => dol_print_date($now, 'standard'),
			'disable_enabled' => $disableEnabled,
			'disable_at' => $disableAt,
			'disable_at_human' => ($disableAt > 0 ? dol_print_date($disableAt, 'standard') : ''),
			'disable_due' => ($disableEnabled && $disableAt > 0 && $now >= $disableAt) ? 1 : 0,
			'delete_enabled' => $deleteEnabled,
			'delete_at' => $deleteAt,
			'delete_at_human' => ($deleteAt > 0 ? dol_print_date($deleteAt, 'standard') : ''),
			'delete_due' => ($deleteEnabled && $deleteAt > 0 && $now >= $deleteAt) ? 1 : 0,
			'syslog_enabled_before' => isModEnabled('syslog') ? 1 : 0,
		));

		// A. Disable syslog module first
		if ($disableEnabled) {
			if ($disableAt > 0 && $now >= $disableAt) {
				LockDebugLogsLogger::log('disable block: deadline reached, executing', LOG_INFO);
				if (isModEnabled('syslog')) {
					$err = unActivateModule('modSyslog', 0);
					$mainModAfter = dolibarr_get_const($this->db, 'MAIN_MODULE_SYSLOG', self::CONST_ENTITY);
					LockDebugLogsLogger::log('unActivateModule(modSyslog) returned', LOG_INFO, array(
						'error_string' => $err,
						'MAIN_MODULE_SYSLOG_entity0' => $mainModAfter,
						'syslog_enabled_after' => isModEnabled('syslog') ? 1 : 0,
					));
					if ($err !== '') {
						$this->error = $err;
						LockDebugLogsLogger::log('disable block failed', LOG_ERR, array('error' => $err));
						return -1;
					}
					$messages[] = $langs->trans('LockDebugLogsActionDisabledSyslog', dol_print_date($now, 'dayhour'));
				} else {
					LockDebugLogsLogger::log('disable block: syslog already off', LOG_INFO);
					$messages[] = $langs->trans('LockDebugLogsActionSyslogAlreadyOff');
				}
				dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED', '0', 'chaine', 0, '', self::CONST_ENTITY);
				$this->clearBlockDeadlineOnly('DISABLE_MODULE');
			} else {
				LockDebugLogsLogger::log('disable block: not due yet or missing AT', LOG_INFO, array(
					'disable_at' => $disableAt,
					'now' => $now,
					'seconds_remaining' => ($disableAt > $now ? ($disableAt - $now) : 0),
				));
			}
		} else {
			LockDebugLogsLogger::log('disable block: not enabled', LOG_DEBUG);
		}

		// B. Delete log file
		if ($deleteEnabled) {
			if ($deleteAt > 0 && $now >= $deleteAt) {
				LockDebugLogsLogger::log('delete block: deadline reached, executing', LOG_INFO);
				$path = $this->getLogFilePathFromConst();
				LockDebugLogsLogger::log('delete block path', LOG_INFO, array(
					'path' => $path,
					'safe' => ($path !== '' && $this->isLogFilePathSafe($path)) ? 1 : 0,
					'exists' => ($path !== '' && dol_is_file($path)) ? 1 : 0,
				));
				if ($path !== '' && $this->isLogFilePathSafe($path)) {
					$result = dol_delete_file($path, 0, 0, 0, null, false, 0);
					LockDebugLogsLogger::log('dol_delete_file result', LOG_INFO, array('result' => $result));
					if ($result) {
						$messages[] = $langs->trans('LockDebugLogsActionDeletedLog', $path);
					} else {
						$this->error = 'LockDebugLogsErrorDeleteLog';
						LockDebugLogsLogger::log('delete block failed', LOG_ERR);
						return -1;
					}
				} elseif ($path !== '' && dol_is_file($path)) {
					$this->error = 'LockDebugLogsErrorUnsafeLogPath';
					LockDebugLogsLogger::log('delete block unsafe path', LOG_WARNING, array('path' => $path));
					return -1;
				} else {
					$messages[] = $langs->trans('LockDebugLogsActionLogNotFound');
				}
				dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_DELETE_FILE_ENABLED', '0', 'chaine', 0, '', self::CONST_ENTITY);
				$this->clearBlockDeadlineOnly('DELETE_FILE');
			} else {
				LockDebugLogsLogger::log('delete block: not due yet or missing AT', LOG_INFO, array(
					'delete_at' => $deleteAt,
					'now' => $now,
				));
			}
		} else {
			LockDebugLogsLogger::log('delete block: not enabled', LOG_DEBUG);
		}

		if (!empty($messages)) {
			$note = implode(' | ', $messages);
			dolibarr_set_const($this->db, 'LOCKDEBUGLOGS_LAST_ACTION_NOTE', $note, 'chaine', 0, '', self::CONST_ENTITY);
			$this->output = $note;
			LockDebugLogsLogger::log('actions performed', LOG_INFO, array('messages' => $messages));
		} else {
			$this->output = 'No action due';
			LockDebugLogsLogger::log('no action due', LOG_INFO);
		}

		LockDebugLogsLogger::log('runScheduledActions end', LOG_INFO, array('output' => $this->output, 'error' => $this->error));

		return 0;
	}
}
