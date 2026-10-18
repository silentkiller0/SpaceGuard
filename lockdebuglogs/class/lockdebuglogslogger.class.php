<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/class/lockdebuglogslogger.class.php
 * \ingroup lockdebuglogs
 * \brief   Diagnostics via Dolibarr syslog (no separate module log file)
 */

/**
 * Logger for LockDebugLogs — uses native dol_syslog only
 */
class LockDebugLogsLogger
{
	/**
	 * Mirror message to Dolibarr debug log when syslog is active.
	 *
	 * @param string               $message Message
	 * @param int                  $level   LOG_* level for dol_syslog
	 * @param array<string,mixed>  $context Extra key/value data (logged as JSON)
	 * @return void
	 */
	public static function log($message, $level = LOG_INFO, $context = array())
	{
		if (!function_exists('dol_syslog')) {
			return;
		}

		$full = 'LockDebugLogs: '.$message;
		if (!empty($context)) {
			$encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($encoded !== false) {
				$full .= ' '.$encoded;
			}
		}

		dol_syslog($full, $level);
	}
}
