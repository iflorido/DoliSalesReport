<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    dolisalesreport/css/dolisalesreport.css.php
 * \ingroup dolisalesreport
 * \brief   CSS file for module DoliSalesReport.
 */

if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

session_cache_limiter('public');

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

header('Content-type: text/css');
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
?>

/* ICONO EN MENU SUPERIOR: grafico de barras (Font Awesome \f080 = chart-bar) */
div.mainmenu.dolisalesreport::before {
	content: "\f080" !important;
	font-family: "Font Awesome 5 Free", "Font Awesome 6 Free" !important;
	font-weight: 900;
}
div.mainmenu.dolisalesreport {
	background-image: none !important;
}

/* DASHBOARD KPI CARDS */
.dsr-kpi-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	margin: 10px 0 20px 0;
}
.dsr-kpi-card {
	flex: 1 1 160px;
	min-width: 150px;
	background: var(--colorbacktitle1, #f4f6f9);
	border: 1px solid var(--bordercolor, #ddd);
	border-radius: 10px;
	padding: 18px 16px;
	text-align: center;
	box-shadow: 0 1px 3px rgba(0,0,0,0.06);
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.dsr-kpi-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 3px 8px rgba(0,0,0,0.12);
}
.dsr-kpi-icon {
	font-size: 1.8em;
	color: var(--colortextlink, #2F5496);
	margin-bottom: 8px;
	display: block;
}
.dsr-kpi-value {
	font-size: 1.5em;
	font-weight: 700;
	color: var(--colortext, #333);
	margin-bottom: 4px;
}
.dsr-kpi-label {
	font-size: 0.85em;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

/* Badge texto libre */
.badge.badge-secondary {
	background-color: #8a8a8a;
	color: #fff;
	padding: 2px 7px;
	border-radius: 6px;
	font-size: 0.75em;
	vertical-align: middle;
}
