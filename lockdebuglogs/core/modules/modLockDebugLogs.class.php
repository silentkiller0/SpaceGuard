<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup   lockdebuglogs Module LockDebugLogs
 * \brief      Safety timers for Debug Logs (syslog)
 *
 * \file       htdocs/custom/lockdebuglogs/core/modules/modLockDebugLogs.class.php
 * \ingroup    lockdebuglogs
 * \brief      Description and activation file for module LockDebugLogs
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module LockDebugLogs
 */
class modLockDebugLogs extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 500001;

		$this->rights_class = 'lockdebuglogs';
		$this->family = 'technic';
		$this->module_position = '77';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Safety timers for Debug Logs (auto-disable module or delete log file)';
		$this->descriptionlong = 'LockDebugLogsDescLong';
		$this->version = '1.1.0';
		$this->editor_name = 'Big Data Consulting';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'lock';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/lockdebuglogs/css/lockdebuglogs.css.php'),
			'js' => array(),
			'hooks' => array(
				'data' => array(
					'main',
					'adminmodules',
				),
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@lockdebuglogs');

		$this->hidden = false;
		$this->depends = array('always' => 'modSyslog');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('lockdebuglogs@lockdebuglogs');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(18, -3);

		$this->warnings_activation = array();
		if (!isModEnabled('cron')) {
			$this->warnings_activation['always'] = 'LockDebugLogsWarnCronDisabled';
		}

		$this->const = array(
			0 => array('LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED', 'chaine', '0', 'Auto-disable Debug Logs module', 0, 'current', 0),
			1 => array('LOCKDEBUGLOGS_DELETE_FILE_ENABLED', 'chaine', '0', 'Auto-delete syslog file', 0, 'current', 0),
		);

		$this->cronjobs = array(
			0 => array(
				'label' => 'LockDebugLogsCheck',
				'jobtype' => 'method',
				'class' => 'lockdebuglogs/class/lockdebuglogscron.class.php',
				'objectname' => 'LockDebugLogsCron',
				'method' => 'runLockDebugLogsJobs',
				'parameters' => '',
				'comment' => 'Disable Debug Logs module or delete log file when deadline is reached',
				'frequency' => 1,
				'unitfrequency' => 300,
				'priority' => 50,
				'status' => 1,
				'test' => 'isModEnabled("lockdebuglogs")',
			),
		);

		if (!isset($conf->lockdebuglogs) || !is_object($conf->lockdebuglogs)) {
			$conf->lockdebuglogs = new stdClass();
			$conf->lockdebuglogs->enabled = 0;
		}
	}

	/**
	 * Function called when module is enabled
	 *
	 * @param string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled
	 *
	 * @param string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');
		$service = new LockDebugLogs($this->db);
		$service->deleteAllConstants();

		$sql = array();
		return $this->_remove($sql, $options);
	}
}
