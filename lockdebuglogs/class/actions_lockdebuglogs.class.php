<?php
/* Copyright (C) 2026
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/lockdebuglogs/class/actions_lockdebuglogs.class.php
 * \ingroup lockdebuglogs
 * \brief   Hooks for LockDebugLogs
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');

/**
 * Hook actions for LockDebugLogs
 */
class ActionsLockDebugLogs extends CommonHookActions
{
	/** @var DoliDB */
	public $db;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Run on every authenticated Dolibarr page (main.inc.php updateSession).
	 * No popup — actions are silent; see trace log and setup "Last automatic action".
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null   $object     Object
	 * @param string|null         $action     Action
	 * @param HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function updateSession($parameters, &$object, &$action, $hookmanager)
	{
		if (empty($parameters['currentcontext']) || !in_array($parameters['currentcontext'], array('main'), true)) {
			return 0;
		}
		if (!isModEnabled('lockdebuglogs')) {
			return 0;
		}

		LockDebugLogs::runSilent($this->db, 'hook_updateSession');

		return 0;
	}

	/**
	 * Fallback when an admin opens the module list (same silent run).
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null   $object     Object
	 * @param string|null         $action     Action
	 * @param HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		if (empty($parameters['currentcontext']) || !in_array($parameters['currentcontext'], array('adminmodules'), true)) {
			return 0;
		}
		if (empty($user->admin) || !isModEnabled('lockdebuglogs')) {
			return 0;
		}

		LockDebugLogs::runSilent($this->db, 'hook_adminmodules');

		return 0;
	}

	/**
	 * Floating status badge on every back-office page.
	 * Shown to admins only when LockDebugLogs and native Debug Logs are both enabled.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null   $object     Object
	 * @param string|null         $action     Action
	 * @param HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!is_object($user) || empty($user->admin) || !isModEnabled('lockdebuglogs') || !isModEnabled('syslog')) {
			return 0;
		}

		$langs->load('lockdebuglogs@lockdebuglogs');

		$service = new LockDebugLogs($this->db);
		$autoDisable = $service->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED');
		$autoDelete = $service->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_ENABLED');
		$safe = ($autoDisable || $autoDelete);

		$details = array();
		if ($autoDisable) {
			$details[] = $langs->trans('LockDebugLogsFloatAutoDisable');
		}
		if ($autoDelete) {
			$details[] = $langs->trans('LockDebugLogsFloatAutoDelete');
		}

		$class = 'lockdebuglogs-float '.($safe ? 'lockdebuglogs-float-ok' : 'lockdebuglogs-float-warn');
		$url = dol_buildpath('/lockdebuglogs/admin/setup.php', 1);
		$css = dol_buildpath('/lockdebuglogs/css/lockdebuglogs.css.php', 1);

		$lead = dol_escape_htmltag($langs->trans('LockDebugLogsFloatLead'));
		$isActive = dol_escape_htmltag($langs->trans('LockDebugLogsFloatIsActive'));
		$setupUrl = dol_escape_htmltag($url);

		$html = '<link rel="stylesheet" type="text/css" href="'.dol_escape_htmltag($css).'">'."\n";
		$html .= '<div id="lockdebuglogs-float" class="'.$class.'">';
		if ($safe) {
			$html .= '<span class="lockdebuglogs-float-title">'.$lead.' <strong>'.$isActive.'</strong></span>';
			$html .= '<span class="lockdebuglogs-float-detail">'.dol_escape_htmltag(implode(' · ', $details)).'</span>';
		} else {
			$html .= '<span class="lockdebuglogs-float-detail">'.$lead.' <strong>'.$isActive.'</strong>. ';
			$html .= dol_escape_htmltag($langs->trans('LockDebugLogsFloatTail')).' ';
			$html .= '<a class="lockdebuglogs-float-link" href="'.$setupUrl.'">'.dol_escape_htmltag($langs->trans('LockDebugLogsFloatSpaceGuard')).'</a>';
			$html .= '</span>';
		}
		$html .= '</div>';

		$this->resprints = $html;

		return 0;
	}
}
