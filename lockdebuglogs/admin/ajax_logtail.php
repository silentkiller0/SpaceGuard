<?php
/* Copyright (C) 2026 */

/**
 * \file    htdocs/custom/lockdebuglogs/admin/ajax_logtail.php
 * \brief   JSON endpoint — fresh tail of Dolibarr SYSLOG file (no cache)
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}

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
	http_response_code(500);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(array('error' => 'Include of main fails'));
	exit;
}

/**
 * @var User $user
 * @var Translate $langs
 * @var DoliDB $db
 */

dol_include_once('/lockdebuglogs/lib/lockdebuglogs.lib.php');
dol_include_once('/lockdebuglogs/class/lockdebuglogs.class.php');

$langs->load('lockdebuglogs@lockdebuglogs');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($user->admin)) {
	http_response_code(403);
	echo json_encode(array('error' => 'Forbidden'));
	exit;
}

$token = GETPOST('token', 'alpha');
if ($token === '' || $token !== currentToken()) {
	http_response_code(403);
	echo json_encode(array('error' => 'Bad token'));
	exit;
}

$service = new LockDebugLogs($db);
$logpath = $service->getLogFilePathFromConst();

clearstatcache(true, $logpath);
$found = ($logpath !== '' && @is_file($logpath));
$mtime = ($found ? (int) @filemtime($logpath) : 0);
$size = ($found ? (int) @filesize($logpath) : 0);

$html = lockdebuglogsBuildLogViewerHtml($logpath, $langs, 40);

echo json_encode(array(
	'html' => $html,
	'found' => $found,
	'mtime' => $mtime,
	'size' => $size,
	'path' => $logpath,
));
