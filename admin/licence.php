<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es> */

/**
 * \file    dolisalesreport/admin/licence.php
 * \ingroup dolisalesreport
 * \brief   License setup page of module DoliSalesReport.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Error critico: No se pudo encontrar main.inc.php");

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/dolisalesreport/lib/dolisalesreport.lib.php');
dol_include_once('/dolisalesreport/core/lib/licence.lib.php');

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}

$langs->loadLangs(array("admin", "dolisalesreport@dolisalesreport"));
if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');
$formSetup = new FormSetup($db);

// --- PARAMETROS ---

// Clave de Licencia
$item = $formSetup->newItem('DOLISALESREPORT_LICENSE_KEY');
$item->nameText = "Clave de Licencia";
$item->helpText = "Introduzca la clave de licencia proporcionada por el desarrollador.";
$item->fieldAttr['placeholder'] = 'XXXX-XXXX-XXXX-XXXX-XXXX-XXXX';
$item->cssClass = 'minwidth500';

// Url de la instalacion
$item = $formSetup->newItem('DOLISALESREPORT_INSTALL_URL');
$item->nameText = "Url de la instalacion";
$item->helpText = "URL actual detectada para esta licencia.";
$item->fieldAttr['placeholder'] = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . DOL_URL_ROOT;
$item->cssClass = 'minwidth500';

// Nueva seccion
$formSetup->newItem('New section')->setAsTitle();

// Descripcion de la instalacion (editor HTML)
$item = $formSetup->newItem('DOLISALESREPORT_INSTALL_DESC')->setAsHtml();
$item->nameText = "Descripcion de la instalacion";
$item->helpText = "Informacion adicional sobre el entorno de instalacion.";
$item->cssClass = 'centpercent';

// Texto estatico del desarrollador
$item = $formSetup->newItem('DOLISALESREPORT_DEV_INFO');
$item->nameText = "Informacion del desarrollador";
$item->helpText = "Informacion de autoria.";
$item->fieldOverride = "Sistema de licencia de aplicacion desarrollado por iflorido@gmail.com";

// Clave de activacion (SHA256 del fichero de licencia)
$archivolicencia = dol_buildpath('/dolisalesreport/core/lib/licence.lib.php', 0);
$hashlicencia = file_exists($archivolicencia) ? hash_file('sha256', $archivolicencia) : 'n/a';

$item = $formSetup->newItem('DOLISALESREPORT_SHA256SUM');
$item->nameText = "Su clave de activacion";
$item->fieldOverride = "SHA256 = " . $hashlicencia;

// --- ACCIONES ---
if ($action == 'update') {
	$formSetup->saveConfFromPost();
	if (function_exists('dolisalesreport_get_license_status')) {
		dolisalesreport_get_license_status(true); // fuerza comprobacion remota
	}
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

// --- VISTA ---
llxHeader('', "Licencia DoliSalesReport");

$head = dolisalesreportAdminPrepareHead();
print dol_get_fiche_head($head, 'licence', "Licencia", -1, "dolisalesreport@dolisalesreport");

// Banner de estado (re-leemos estado actual)
if (function_exists('dolisalesreport_get_license_status')) {
	$current_status = dolisalesreport_get_license_status(false);
	dolisalesreport_print_license_banner($current_status);
}

print '<span class="opacitymedium">DoliSalesReport license setup page</span><br><br>';

print $formSetup->generateOutput(true);

print dol_get_fiche_end();
llxFooter();
$db->close();
