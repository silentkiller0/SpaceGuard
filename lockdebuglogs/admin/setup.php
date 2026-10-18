<?php
/* Copyright (C) 2026 */

/**
 * \file    htdocs/custom/lockdebuglogs/admin/setup.php
 * \ingroup lockdebuglogs
 * \brief   LockDebugLogs setup page
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lockdebuglogs/lib/lockdebuglogs.lib.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogslogger.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'lockdebuglogs@lockdebuglogs'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$service = new LockDebugLogs($db);
$durations = lockdebuglogsGetDurationChoices();

/*
 * Actions
 */
if ($action == 'runnow') {
	LockDebugLogs::runAndNotify($db, 'setup_runnow');
}

if ($action == 'update') {
	$error = 0;

	$disableEnabled = GETPOSTINT('disable_module_enabled');
	$disableMode = GETPOST('disable_module_mode', 'aZ09');
	$disableDuration = GETPOSTINT('disable_module_duration');
	$disableDatetime = GETPOST('disable_module_datetime', 'alphanohtml');
	$disableDatetime = str_replace('T', ' ', $disableDatetime);

	$deleteEnabled = GETPOSTINT('delete_file_enabled');
	$deleteMode = GETPOST('delete_file_mode', 'aZ09');
	$deleteDuration = GETPOSTINT('delete_file_duration');
	$deleteDatetime = GETPOST('delete_file_datetime', 'alphanohtml');
	$deleteDatetime = str_replace('T', ' ', $deleteDatetime);

	$res = $service->saveBlockConfig('DISABLE_MODULE', $disableEnabled, $disableMode, $disableDuration, $disableDatetime);
	if ($res < 0) {
		$error++;
		setEventMessages($langs->trans('LockDebugLogsInvalidDeadline'), null, 'errors');
	}

	if (!$error) {
		$res = $service->saveBlockConfig('DELETE_FILE', $deleteEnabled, $deleteMode, $deleteDuration, $deleteDatetime);
		if ($res < 0) {
			$error++;
			setEventMessages($langs->trans('LockDebugLogsInvalidDeadline'), null, 'errors');
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('LockDebugLogsSetupSaved'), null, 'mesgs');
		LockDebugLogsLogger::log('setup saved by user', LOG_INFO, $service->getDiagnosticsSnapshot());
	}
}

LockDebugLogs::runSilent($db, 'setup_page_load');

/*
 * View — load saved form values after optional silent run
 */
$disableEnabled = $service->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_ENABLED');
$disableMode = $service->getModuleConst('LOCKDEBUGLOGS_DISABLE_MODULE_MODE', 'duration');
if ($disableMode === '') {
	$disableMode = 'duration';
}
$disableDuration = $service->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_DURATION', 1800);
if ($disableDuration <= 0) {
	$disableDuration = 1800;
}
$disableAt = $service->getModuleConstInt('LOCKDEBUGLOGS_DISABLE_MODULE_AT');
$disableDatetime = lockdebuglogsFormatDatetimeLocal($service->getModuleConst('LOCKDEBUGLOGS_DISABLE_MODULE_DATETIME', ''), $disableAt);

$deleteEnabled = $service->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_ENABLED');
$deleteMode = $service->getModuleConst('LOCKDEBUGLOGS_DELETE_FILE_MODE', 'duration');
if ($deleteMode === '') {
	$deleteMode = 'duration';
}
$deleteDuration = $service->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_DURATION', 1800);
if ($deleteDuration <= 0) {
	$deleteDuration = 1800;
}
$deleteAt = $service->getModuleConstInt('LOCKDEBUGLOGS_DELETE_FILE_AT');
$deleteDatetime = lockdebuglogsFormatDatetimeLocal($service->getModuleConst('LOCKDEBUGLOGS_DELETE_FILE_DATETIME', ''), $deleteAt);

$help_url = '';
$arrayofcss = array('/lockdebuglogs/css/lockdebuglogs.css.php');
llxHeader('', $langs->trans('LockDebugLogsSetupTitle'), $help_url, '', 0, 0, '', $arrayofcss, '', 'mod-lockdebuglogs page-setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('LockDebugLogsSetupTitle'), $linkback, 'title_setup');

$head = lockdebuglogsAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('Module500001Name'), -1, 'lock');

$logpath = $service->getLogFilePathFromConst();
$lastnote = $service->getModuleConst('LOCKDEBUGLOGS_LAST_ACTION_NOTE', '');
$syslogOn = isModEnabled('syslog');
$cronOn = isModEnabled('cron');

// —— Status card ——
print '<div class="lockdebuglogs-status-card">';
print '<div class="lockdebuglogs-status-grid">';

print '<div class="lockdebuglogs-status-item">';
print '<span class="lockdebuglogs-status-label">'.$langs->trans('LockDebugLogsStatusDebugLogs').'</span>';
if ($syslogOn) {
	print '<span class="lockdebuglogs-badge lockdebuglogs-badge-ok">'.$langs->trans('LockDebugLogsBadgeActive').'</span>';
} else {
	print '<span class="lockdebuglogs-badge lockdebuglogs-badge-off">'.$langs->trans('LockDebugLogsBadgeOff').'</span>';
	print ' <a class="lockdebuglogs-link" href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=Debug">'.$langs->trans('LockDebugLogsEnableModule').'</a>';
}
print '</div>';

print '<div class="lockdebuglogs-status-item">';
print '<span class="lockdebuglogs-status-label">'.$langs->trans('LockDebugLogsStatusCron').'</span>';
if ($cronOn) {
	print '<span class="lockdebuglogs-badge lockdebuglogs-badge-ok">'.$langs->trans('LockDebugLogsBadgeActive').'</span>';
} else {
	print '<span class="lockdebuglogs-badge lockdebuglogs-badge-warn">'.$langs->trans('LockDebugLogsBadgeRecommended').'</span>';
	print ' <a class="lockdebuglogs-link" href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=Scheduled">'.$langs->trans('LockDebugLogsEnableModule').'</a>';
}
print '</div>';

print '<div class="lockdebuglogs-status-item">';
print '<span class="lockdebuglogs-status-label">'.$langs->trans('LockDebugLogsServerNowLabel').'</span>';
print '<span class="lockdebuglogs-status-value">'.dol_print_date(dol_now(), 'dayhour').'</span>';
print '</div>';

if ($syslogOn) {
	print '<div class="lockdebuglogs-status-item">';
	print '<span class="lockdebuglogs-status-label">'.$langs->trans('LockDebugLogsSyslogLevel').'</span>';
	print '<span class="lockdebuglogs-status-value">'.dol_escape_htmltag(getDolGlobalString('SYSLOG_LEVEL')).'</span>';
	print '</div>';
}

print '</div>';

if ($lastnote !== '') {
	print '<div class="lockdebuglogs-last-action"><span class="lockdebuglogs-status-label">'.$langs->trans('LockDebugLogsLastAction').'</span> '.dol_escape_htmltag($lastnote).'</div>';
}

if (!$syslogOn || !$cronOn) {
	print '<div class="lockdebuglogs-status-callout">';
	if (!$syslogOn) {
		print '<p>'.$langs->trans('LockDebugLogsDebugHintShort').'</p>';
	}
	if (!$cronOn) {
		print '<p>'.$langs->trans('LockDebugLogsCronHintShort').'</p>';
	}
	print '</div>';
}

print '<div class="lockdebuglogs-actions-row">';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=runnow&token='.newToken().'">'.$langs->trans('LockDebugLogsExecuteNow').'</a>';
print '<a class="butAction marginleftonly" href="'.DOL_URL_ROOT.'/admin/syslog.php">'.$langs->trans('LockDebugLogsOpenSyslogSetup').'</a>';
print '</div>';
print '</div>';

// —— Safeguard form (two cards) ——
print '<form method="post" action="'.$_SERVER['PHP_SELF'].'" class="lockdebuglogs-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="lockdebuglogs-cards-row">';

// Card disable
print '<div class="lockdebuglogs-card lockdebuglogs-card-disable">';
print '<h3 class="lockdebuglogs-card-title">'.$langs->trans('LockDebugLogsSectionDisable').'</h3>';
print '<p class="lockdebuglogs-card-desc">'.$langs->trans('LockDebugLogsSectionDisableHelp').'</p>';

print '<label class="lockdebuglogs-check-line"><input type="checkbox" name="disable_module_enabled" value="1"'.($disableEnabled ? ' checked' : '').'> '.$langs->trans('LockDebugLogsEnableFeature').'</label>';

print '<div class="lockdebuglogs-field-row">';
print '<label class="lockdebuglogs-radio-line"><input type="radio" name="disable_module_mode" value="duration"'.($disableMode !== 'datetime' ? ' checked' : '').'> '.$langs->trans('LockDebugLogsModeDuration').'</label>';
print '<select class="flat maxwidth150" name="disable_module_duration">';
foreach ($durations as $sec => $langkey) {
	print '<option value="'.$sec.'"'.($disableDuration == $sec ? ' selected' : '').'>'.$langs->trans($langkey).'</option>';
}
print '</select></div>';

print '<div class="lockdebuglogs-field-row">';
print '<label class="lockdebuglogs-radio-line"><input type="radio" name="disable_module_mode" value="datetime"'.($disableMode === 'datetime' ? ' checked' : '').'> '.$langs->trans('LockDebugLogsModeDatetime').'</label>';
print '<input type="datetime-local" class="flat lockdebuglogs-datetime" name="disable_module_datetime" value="'.dol_escape_htmltag($disableDatetime).'">';
print '</div>';

print '<div class="lockdebuglogs-deadline-row"><span class="lockdebuglogs-deadline-label">'.$langs->trans('LockDebugLogsDeadline').'</span> ';
print lockdebuglogsPrintDeadlineBadge($langs, $disableEnabled, $disableAt);
print '</div></div>';

// Card delete
print '<div class="lockdebuglogs-card lockdebuglogs-card-delete">';
print '<h3 class="lockdebuglogs-card-title">'.$langs->trans('LockDebugLogsSectionDelete').'</h3>';
print '<p class="lockdebuglogs-card-desc">'.$langs->trans('LockDebugLogsSectionDeleteHelp').'</p>';

print '<label class="lockdebuglogs-check-line"><input type="checkbox" name="delete_file_enabled" value="1"'.($deleteEnabled ? ' checked' : '').'> '.$langs->trans('LockDebugLogsEnableFeature').'</label>';

print '<div class="lockdebuglogs-field-row">';
print '<label class="lockdebuglogs-radio-line"><input type="radio" name="delete_file_mode" value="duration"'.($deleteMode !== 'datetime' ? ' checked' : '').'> '.$langs->trans('LockDebugLogsModeDeleteDuration').'</label>';
print '<select class="flat maxwidth150" name="delete_file_duration">';
foreach ($durations as $sec => $langkey) {
	print '<option value="'.$sec.'"'.($deleteDuration == $sec ? ' selected' : '').'>'.$langs->trans($langkey).'</option>';
}
print '</select></div>';

print '<div class="lockdebuglogs-field-row">';
print '<label class="lockdebuglogs-radio-line"><input type="radio" name="delete_file_mode" value="datetime"'.($deleteMode === 'datetime' ? ' checked' : '').'> '.$langs->trans('LockDebugLogsModeDeleteDatetime').'</label>';
print '<input type="datetime-local" class="flat lockdebuglogs-datetime" name="delete_file_datetime" value="'.dol_escape_htmltag($deleteDatetime).'">';
print '</div>';

print '<div class="lockdebuglogs-deadline-row"><span class="lockdebuglogs-deadline-label">'.$langs->trans('LockDebugLogsDeadline').'</span> ';
print lockdebuglogsPrintDeadlineBadge($langs, $deleteEnabled, $deleteAt);
print '</div></div>';

print '</div>'; // cards-row

print '<div class="center lockdebuglogs-save-row"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

// —— Dolibarr debug log (SYSLOG_FILE) ——
print '<div class="lockdebuglogs-log-section" id="lockdebuglogs-log-section">';
print '<p class="lockdebuglogs-log-intro">'.$langs->trans('LockDebugLogsLogIntro').'</p>';
print '<p class="lockdebuglogs-log-path opacitymedium"><code>'.dol_escape_htmltag($logpath).'</code>';
if (dol_is_file($logpath)) {
	print ' · <a href="'.DOL_URL_ROOT.'/document.php?modulepart=logs&file='.urlencode(basename($logpath)).'">'.$langs->trans('Download').'</a>';
}
print '</p>';

print '<div class="lockdebuglogs-log-viewer" id="lockdebuglogs-log-viewer">';
print lockdebuglogsBuildLogViewerHtml($logpath, $langs, 40);
print '</div>';
print '<div class="lockdebuglogs-log-footer center">';
print '<button type="button" class="butAction" id="lockdebuglogs-reload-logs">'.$langs->trans('LockDebugLogsReloadLogs').'</button>';
print '<span class="lockdebuglogs-log-sync opacitymedium" id="lockdebuglogs-log-sync"></span>';
print '</div></div>';

$ajaxLogUrl = dol_buildpath('/lockdebuglogs/admin/ajax_logtail.php', 1);
print '<script>
(function () {
	var ajaxUrl = '.json_encode($ajaxLogUrl).';
	var token = '.json_encode(currentToken()).';
	var viewer = document.getElementById("lockdebuglogs-log-viewer");
	var syncEl = document.getElementById("lockdebuglogs-log-sync");
	var btn = document.getElementById("lockdebuglogs-reload-logs");
	if (!viewer || !btn) { return; }

	function scrollViewerToBottom() {
		viewer.scrollTop = viewer.scrollHeight;
	}

	function setSyncLabel(mtime) {
		if (!syncEl) { return; }
		if (mtime > 0) {
			syncEl.textContent = " · " + new Date(mtime * 1000).toLocaleString();
		} else {
			syncEl.textContent = "";
		}
	}

	function reloadLogs() {
		btn.disabled = true;
		viewer.classList.add("lockdebuglogs-log-loading");
		var url = ajaxUrl + "?token=" + encodeURIComponent(token) + "&t=" + Date.now();
		fetch(url, { method: "GET", credentials: "same-origin", cache: "no-store", headers: { "Accept": "application/json" } })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && typeof data.html === "string") {
					viewer.innerHTML = data.html;
					setSyncLabel(data.mtime || 0);
					scrollViewerToBottom();
				}
			})
			.catch(function () { /* keep current content */ })
			.finally(function () {
				btn.disabled = false;
				viewer.classList.remove("lockdebuglogs-log-loading");
			});
	}

	btn.addEventListener("click", function (e) {
		e.preventDefault();
		reloadLogs();
	});

	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "visible") {
			reloadLogs();
		}
	});

	scrollViewerToBottom();
	reloadLogs();
})();
</script>';

print dol_get_fiche_end();

llxFooter();
$db->close();
