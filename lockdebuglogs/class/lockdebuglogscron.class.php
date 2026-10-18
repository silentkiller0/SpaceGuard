<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/class/lockdebuglogscron.class.php
 * \ingroup lockdebuglogs
 * \brief   Cron wrapper for LockDebugLogs
 */

dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');

/**
 * Cron jobs for LockDebugLogs
 */
class LockDebugLogsCron
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string */
	public $output = '';

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
	 * Execute scheduled safety actions (cron)
	 *
	 * @return int 0 if OK
	 */
	public function runLockDebugLogsJobs()
	{
		$service = new LockDebugLogs($this->db);
		$result = $service->runScheduledActions('cron');
		$this->error = $service->error;
		$this->output = $service->output;
		return $result;
	}
}
