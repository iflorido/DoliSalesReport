<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es> */

/**
 * \file    dolisalesreport/admin/setup.php
 * \ingroup dolisalesreport
 * \brief   Setup page of module DoliSalesReport.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Error: No se pudo cargar main.inc.php");

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/dolisalesreport/lib/dolisalesreport.lib.php');
dol_include_once('/dolisalesreport/core/lib/licence.lib.php');

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}

$langs->loadLangs(array("admin", "dolisalesreport@dolisalesreport"));
if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');

/* --- LICENCIA --- */
$status = function_exists('dolisalesreport_get_license_status') ? dolisalesreport_get_license_status(false) : 'not_checked';
$is_license_valid = ($status == 'valid');

$formSetup = new FormSetup($db);

// Numero de lineas por pagina por defecto
$item = $formSetup->newItem('DOLISALESREPORT_DEFAULT_LIMIT');
$item->nameText = $langs->trans("DefaultLinesPerPage");
$item->helpText = $langs->trans("DefaultLinesPerPageHelp");
$item->fieldAttr['placeholder'] = '25';
$item->cssClass = 'minwidth100';

if ($action == 'update' && $is_license_valid) {
	$formSetup->saveConfFromPost();
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

llxHeader('', $langs->trans("DoliSalesReportSetup"));

$head = dolisalesreportAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleDoliSalesReportName"), -1, "dolisalesreport@dolisalesreport");

if (!$is_license_valid) {
	dolisalesreport_print_license_banner($status);
}

print '<span class="opacitymedium">'.$langs->trans("DoliSalesReportSetupPage").'</span><br><br>';

print $formSetup->generateOutput($is_license_valid);

print dol_get_fiche_end();
llxFooter();
$db->close();
