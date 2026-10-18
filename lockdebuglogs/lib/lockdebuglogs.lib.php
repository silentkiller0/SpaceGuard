<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/lib/lockdebuglogs.lib.php
 * \ingroup lockdebuglogs
 * \brief   Library for LockDebugLogs module
 */

/**
 * Prepare admin pages header
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function lockdebuglogsAdminPrepareHead()
{
	global $conf, $langs;

	$langs->load('lockdebuglogs@lockdebuglogs');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/lockdebuglogs/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'lockdebuglogs@lockdebuglogs');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'lockdebuglogs@lockdebuglogs', 'remove');

	return $head;
}

/**
 * Duration choices (seconds => label lang key)
 *
 * @return array<int,string>
 */
function lockdebuglogsGetDurationChoices()
{
	return array(
		300 => 'LockDebugLogsDuration5min',
		600 => 'LockDebugLogsDuration10min',
		900 => 'LockDebugLogsDuration15min',
		1800 => 'LockDebugLogsDuration30min',
		3600 => 'LockDebugLogsDuration1h',
		7200 => 'LockDebugLogsDuration2h',
		14400 => 'LockDebugLogsDuration4h',
		28800 => 'LockDebugLogsDuration8h',
		86400 => 'LockDebugLogsDuration24h',
	);
}

/**
 * Format stored datetime for HTML datetime-local input.
 *
 * @param string $value      Value from database
 * @param int    $fallbackTs Deadline timestamp if value empty
 * @return string
 */
function lockdebuglogsFormatDatetimeLocal($value, $fallbackTs = 0)
{
	$value = trim(str_replace('T', ' ', (string) $value));
	if ($value === '' && $fallbackTs > 0) {
		$value = dol_print_date($fallbackTs, '%Y-%m-%d %H:%M:%S', 'tzuser');
	}
	if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(?::(\d{2}))?/', $value, $m)) {
		return $m[1].'T'.$m[2].(!empty($m[3]) ? ':'.$m[3] : '');
	}

	return preg_replace('/\s/', 'T', $value, 1);
}

/**
 * CSS class for log line status token
 *
 * @param string $status Status text
 * @return string
 */
function lockdebuglogsGetLogStatusClass($status)
{
	$level = strtoupper(trim($status));

	$errorLevels = array('EMERG', 'ALERT', 'CRIT', 'ERR', 'ERROR');
	$warningLevels = array('WARNING', 'WARN', 'NOTICE');
	$infoLevels = array('INFO');
	$debugLevels = array('DEBUG');
	$successLevels = array('OK', 'SUCCESS');

	if (in_array($level, $errorLevels, true)) {
		return 'lockdebuglogs-log-status-error';
	}
	if (in_array($level, $warningLevels, true)) {
		return 'lockdebuglogs-log-status-warning';
	}
	if (in_array($level, $infoLevels, true)) {
		return 'lockdebuglogs-log-status-info';
	}
	if (in_array($level, $debugLevels, true)) {
		return 'lockdebuglogs-log-status-debug';
	}
	if (in_array($level, $successLevels, true)) {
		return 'lockdebuglogs-log-status-success';
	}

	return 'lockdebuglogs-log-status-plain';
}

/**
 * Read last lines from a Dolibarr log file (reads only the tail of large files).
 *
 * @param string $path     Full path to log file
 * @param int    $maxLines Number of lines to return
 * @return string[]
 */
function lockdebuglogsReadLogTailLines($path, $maxLines = 40)
{
	if ($path === '') {
		return array();
	}

	clearstatcache(true, $path);

	if (!@is_readable($path) || !@is_file($path)) {
		return array();
	}

	$maxLines = max(1, (int) $maxLines);
	$size = @filesize($path);
	if ($size === false || $size === 0) {
		return array();
	}

	$handle = @fopen($path, 'rb');
	if ($handle === false) {
		return array();
	}

	$chunkSize = 8192;
	$buffer = '';
	$position = $size;
	$linesNeeded = $maxLines + 1;

	while ($position > 0 && substr_count($buffer, "\n") < $linesNeeded) {
		$read = min($chunkSize, $position);
		$position -= $read;
		fseek($handle, $position);
		$chunk = fread($handle, $read);
		if ($chunk === false) {
			break;
		}
		$buffer = $chunk.$buffer;
	}
	fclose($handle);

	if ($buffer === '') {
		return array();
	}

	$lines = preg_split("/\r\n|\n|\r/", $buffer);
	if (!is_array($lines)) {
		return array();
	}

	if ($position > 0 && count($lines) > 0) {
		array_shift($lines);
	}

	$lines = array_values(array_filter($lines, static function ($line) {
		return $line !== '';
	}));

	return array_slice($lines, -1 * $maxLines);
}

/**
 * HTML for log viewer body (last lines of SYSLOG file).
 *
 * @param string    $logpath Full path
 * @param Translate $langs   Lang
 * @param int       $maxLines Max lines
 * @return string
 */
function lockdebuglogsBuildLogViewerHtml($logpath, $langs, $maxLines = 40)
{
	$logfileExists = ($logpath !== '' && @is_file($logpath));
	if (!$logfileExists) {
		return '<div class="lockdebuglogs-log-empty">'.dol_escape_htmltag($langs->trans('LockDebugLogsLogFileNotFound', $logpath)).'</div>';
	}

	$taillines = lockdebuglogsReadLogTailLines($logpath, $maxLines);
	if (empty($taillines)) {
		return '<div class="lockdebuglogs-log-empty">'.dol_escape_htmltag($langs->trans('LockDebugLogsTraceLogEmpty')).'</div>';
	}

	$html = '';
	foreach ($taillines as $logline) {
		$html .= lockdebuglogsRenderLogLineHtml($logline);
	}

	return $html;
}

/**
 * Render one trace log line as HTML
 *
 * @param string $line Raw log line
 * @return string
 */
function lockdebuglogsRenderLogLineHtml($line)
{
	$datetime = '';
	$status = '';
	$payload = '';

	if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\s+\d+\.\d{3})?\s+(\S+)\s+(.+)$/', trim($line), $m)) {
		$datetime = $m[1];
		$status = trim($m[2]);
		$payload = $m[3];
	} elseif (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+(.+)$/', $line, $m)) {
		$datetime = $m[1];
		$rest = $m[2];
		$status = $rest;
		$payload = '';

		if (strpos($rest, ' | ') !== false) {
			$parts = explode(' | ', $rest, 2);
			$status = $parts[0];
			$payload = $parts[1];
		}
	} else {
		return '<div class="lockdebuglogs-log-line"><span class="lockdebuglogs-log-msg">'.dol_escape_htmltag($line).'</span></div>';
	}

	$class = lockdebuglogsGetLogStatusClass($status);
	$html = '<div class="lockdebuglogs-log-line">';
	$html .= '<span class="lockdebuglogs-log-ts">'.dol_escape_htmltag($datetime).'</span> ';
	$html .= '<span class="lockdebuglogs-log-status '.$class.'">'.dol_escape_htmltag($status).'</span>';
	if ($payload !== '') {
		$html .= ' <span class="lockdebuglogs-log-payload">'.dol_escape_htmltag($payload).'</span>';
	}
	$html .= '</div>';

	return $html;
}

/**
 * Print deadline badge HTML
 *
 * @param Translate $langs Lang
 * @param int         $enabled 0|1
 * @param int         $at      Timestamp
 * @return string
 */
function lockdebuglogsPrintDeadlineBadge($langs, $enabled, $at)
{
	if (!$enabled || $at <= 0) {
		return '<span class="lockdebuglogs-deadline lockdebuglogs-deadline-none">'.$langs->trans('LockDebugLogsNoDeadline').'</span>';
	}

	$nowts = dol_now();
	$label = dol_print_date($at, 'dayhour');
	if ($at > $nowts) {
		$meta = $langs->trans('LockDebugLogsInSeconds', ($at - $nowts));

		return '<span class="lockdebuglogs-deadline lockdebuglogs-deadline-pending"><span class="lockdebuglogs-deadline-when">'.dol_escape_htmltag($label).'</span><span class="lockdebuglogs-deadline-meta">('.$meta.')</span></span>';
	}

	return '<span class="lockdebuglogs-deadline lockdebuglogs-deadline-overdue"><span class="lockdebuglogs-deadline-when">'.dol_escape_htmltag($label).'</span><span class="lockdebuglogs-deadline-meta">('.$langs->trans('LockDebugLogsOverdue').')</span></span>';
}

function lockdebuglogsGetConstNames()
{
	return array(
		'LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED',
		'LOCKDEBUGLOGS_DISABLE_MODULE_MODE',
		'LOCKDEBUGLOGS_DISABLE_MODULE_DURATION',
		'LOCKDEBUGLOGS_DISABLE_MODULE_DATETIME',
		'LOCKDEBUGLOGS_DISABLE_MODULE_AT',
		'LOCKDEBUGLOGS_DELETE_FILE_ENABLED',
		'LOCKDEBUGLOGS_DELETE_FILE_MODE',
		'LOCKDEBUGLOGS_DELETE_FILE_DURATION',
		'LOCKDEBUGLOGS_DELETE_FILE_DATETIME',
		'LOCKDEBUGLOGS_DELETE_FILE_AT',
		'LOCKDEBUGLOGS_LAST_ACTION_NOTE',
	);
}
