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
// The two safeguard blocks: constant prefix => form field prefix, card CSS class, lang keys.
$blockDefs = array(
	'DISABLE_MODULE' => array(
		'field' => 'disable_module',
		'class' => 'lockdebuglogs-card-disable',
		'title' => 'LockDebugLogsSectionDisable',
		'help' => 'LockDebugLogsSectionDisableHelp',
		'modeDuration' => 'LockDebugLogsModeDuration',
		'modeDatetime' => 'LockDebugLogsModeDatetime',
	),
	'DELETE_FILE' => array(
		'field' => 'delete_file',
		'class' => 'lockdebuglogs-card-delete',
		'title' => 'LockDebugLogsSectionDelete',
		'help' => 'LockDebugLogsSectionDeleteHelp',
		'modeDuration' => 'LockDebugLogsModeDeleteDuration',
		'modeDatetime' => 'LockDebugLogsModeDeleteDatetime',
	),
);

// "Execute it now" changes state: POST only (the token is checked by main.inc.php).
if ($action == 'runnow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	LockDebugLogs::runAndNotify($db, 'setup_runnow');
}

if ($action == 'update') {
	$error = 0;

	foreach ($blockDefs as $prefix => $def) {
		$f = $def['field'];
		$res = $service->saveBlockConfig(
			$prefix,
			GETPOSTINT($f.'_enabled'),
			GETPOST($f.'_mode', 'aZ09'),
			GETPOSTINT($f.'_duration'),
			str_replace('T', ' ', GETPOST($f.'_datetime', 'alphanohtml'))
		);
		if ($res < 0) {
			$error++;
			setEventMessages($langs->trans('LockDebugLogsInvalidDeadline'), null, 'errors');
			break;
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
$settings = array();
foreach (array_keys($blockDefs) as $prefix) {
	$settings[$prefix] = lockdebuglogsLoadBlockSettings($service, $prefix);
}

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
print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="runnow">';
print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('LockDebugLogsExecuteNow')).'">';
print '</form>';
print '<a class="butAction marginleftonly" href="'.DOL_URL_ROOT.'/admin/syslog.php">'.$langs->trans('LockDebugLogsOpenSyslogSetup').'</a>';
print '</div>';
print '</div>';

// —— Safeguard form (two cards) ——
print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="lockdebuglogs-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="lockdebuglogs-cards-row">';

foreach ($blockDefs as $prefix => $def) {
	print lockdebuglogsPrintBlockCard($langs, $def, $settings[$prefix], $durations);
}

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
