<?php
/* Copyright (C) 2026 */

if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die();
}

header('Content-Type: text/css');

?>
/* LockDebugLogs setup */
.mod-lockdebuglogs.page-setup .fichecenter {
	max-width: 100%;
	overflow-x: hidden;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-card {
	background: var(--colorbacktitle1, #f4f4f4);
	border: 1px solid var(--colortextbackhmenu, #ddd);
	border-radius: 10px;
	padding: 1rem 1.25rem;
	margin-bottom: 1.25rem;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-callout {
	margin-top: 0.85rem;
	padding: 0.65rem 0.85rem;
	border-radius: 8px;
	background: rgba(199, 120, 0, 0.12);
	border-left: 3px solid #c77800;
	font-size: 0.9em;
	line-height: 1.45;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-callout p {
	margin: 0.35rem 0;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-callout p:first-child {
	margin-top: 0;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-callout p:last-child {
	margin-bottom: 0;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 1rem 2rem;
	margin-bottom: 0.75rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-item {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
	min-width: 12rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-label {
	font-size: 0.85em;
	opacity: 0.75;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-value {
	font-weight: 600;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-badge {
	display: inline-block;
	padding: 0.15rem 0.55rem;
	border-radius: 999px;
	font-size: 0.78em;
	font-weight: 600;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-badge-ok {
	background: #1a7f4b;
	color: #fff;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-badge-off {
	background: #666;
	color: #fff;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-badge-warn {
	background: #c77800;
	color: #fff;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-link {
	font-size: 0.9em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-last-action {
	margin-top: 0.5rem;
	padding-top: 0.5rem;
	border-top: 1px solid var(--colortextbackhmenu, #ddd);
	font-size: 0.92em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-status-hint {
	margin: 0.75rem 0 0;
	font-size: 0.9em;
	opacity: 0.85;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-actions-row {
	margin-top: 1rem;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: center;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-cards-row {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1rem;
	margin-bottom: 1rem;
}

@media (max-width: 900px) {
	.mod-lockdebuglogs.page-setup .lockdebuglogs-cards-row {
		grid-template-columns: 1fr;
	}
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-card {
	background: var(--colorbackbody, #fff);
	border: 2px solid var(--colortextbackhmenu, #ddd);
	border-radius: 10px;
	padding: 1rem 1.1rem;
	min-width: 0;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-card-disable {
	border-color: #3b82f6;
	border-left-width: 5px;
	border-left-color: #2563eb;
	background: linear-gradient(135deg, rgba(59, 130, 246, 0.06) 0%, var(--colorbackbody, #fff) 42%);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-card-delete {
	border-color: #f59e0b;
	border-left-width: 5px;
	border-left-color: #d97706;
	background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, var(--colorbackbody, #fff) 42%);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-card-title {
	margin: 0 0 0.35rem;
	font-size: 1.05em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-card-desc {
	margin: 0 0 0.85rem;
	font-size: 0.88em;
	opacity: 0.8;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-check-line,
.mod-lockdebuglogs.page-setup .lockdebuglogs-radio-line {
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	margin-bottom: 0.5rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-field-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem 0.75rem;
	margin-bottom: 0.65rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-datetime {
	max-width: min(100%, 17rem);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-row {
	margin-top: 0.85rem;
	padding-top: 0.75rem;
	border-top: 1px dashed var(--colortextbackhmenu, #ccc);
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem 0.65rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-label {
	font-size: 0.85em;
	opacity: 0.8;
	flex: 0 0 auto;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline {
	display: inline-flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 0.35rem 0.5rem;
	padding: 0.45rem 0.85rem;
	border-radius: 8px;
	font-weight: 700;
	font-size: 0.95em;
	line-height: 1.35;
	box-decoration-break: clone;
	-webkit-box-decoration-break: clone;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-pending {
	background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);
	color: #fff;
	box-shadow: 0 2px 6px rgba(21, 128, 61, 0.35);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-overdue {
	background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
	color: #fff;
	box-shadow: 0 2px 6px rgba(192, 57, 43, 0.35);
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-none {
	opacity: 0.55;
	font-weight: 500;
	display: inline-block;
	padding: 0.25rem 0;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-when,
.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-meta {
	color: inherit;
	font-weight: inherit;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-meta {
	font-size: 0.88em;
	font-weight: 600;
	opacity: 0.95;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-pending .lockdebuglogs-deadline-meta,
.mod-lockdebuglogs.page-setup .lockdebuglogs-deadline-overdue .lockdebuglogs-deadline-meta {
	color: #fff;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-save-row {
	margin-bottom: 1.5rem;
}

/* Log viewer — dark terminal style, works in Dolibarr dark mode */
.mod-lockdebuglogs.page-setup .lockdebuglogs-log-section {
	margin-top: 0.5rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-intro {
	margin: 0 0 0.35rem;
	font-size: 0.95em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-path {
	margin: 0 0 0.65rem;
	font-size: 0.82em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-footer {
	margin-top: 0.75rem;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: 0.35rem 0.75rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-sync {
	font-size: 0.82em;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-viewer.lockdebuglogs-log-loading {
	opacity: 0.72;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-viewer {
	background: #1a1d23;
	color: #d4d4d4;
	border-radius: 8px;
	padding: 0.75rem 0.85rem;
	max-height: 22rem;
	overflow: auto;
	font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
	font-size: 0.78rem;
	line-height: 1.45;
	border: 1px solid #333;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-empty {
	opacity: 0.65;
	font-style: italic;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-line {
	margin-bottom: 0.45rem;
	word-break: break-word;
	overflow-wrap: anywhere;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-ts {
	color: #7dd3fc;
	font-weight: 600;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status {
	font-weight: 700;
	margin-right: 0.35rem;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-error {
	color: #f87171;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-success {
	color: #4ade80;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-warning {
	color: #fbbf24;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-info {
	color: #60a5fa;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-debug {
	color: #a8a29e;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-status-plain {
	color: #d4d4d4;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-payload {
	color: #e2e8f0;
	font-weight: 400;
	opacity: 0.92;
}

.mod-lockdebuglogs.page-setup .lockdebuglogs-log-msg {
	color: #e5e5e5;
}

/* Floating badge on every back-office page (not scoped to the setup page). */
.lockdebuglogs-float {
	position: fixed;
	left: 50%;
	bottom: 14px;
	transform: translateX(-50%);
	z-index: 2000;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: 0.45rem 0.65rem;
	max-width: min(92vw, 680px);
	padding: 0.4rem 0.45rem 0.4rem 0.9rem;
	border-radius: 999px;
	border: 1px solid rgba(255, 255, 255, 0.38);
	backdrop-filter: blur(12px);
	-webkit-backdrop-filter: blur(12px);
	font-size: 12px;
	line-height: 1.35;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
}

.lockdebuglogs-float-warn {
	background: rgba(245, 158, 11, 0.82);
	color: #3b2500;
}

.lockdebuglogs-float-ok {
	background: rgba(22, 163, 74, 0.82);
	color: #052e16;
}

.lockdebuglogs-float-title {
	font-weight: 700;
	white-space: nowrap;
}

.lockdebuglogs-float-detail {
	opacity: 0.95;
}

.lockdebuglogs-float-link {
	padding: 0.28rem 0.75rem;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.55);
	color: inherit;
	text-decoration: none;
	font-weight: 600;
	white-space: nowrap;
}

.lockdebuglogs-float-link:hover {
	background: rgba(255, 255, 255, 0.8);
	color: inherit;
}
