<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es> */

/**
 * \file    dolisalesreport/admin/about.php
 * \ingroup dolisalesreport
 * \brief   About page of module DoliSalesReport.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Error: No se pudo cargar main.inc.php");

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
dol_include_once('/dolisalesreport/lib/dolisalesreport.lib.php');

$langs->loadLangs(array("admin", "dolisalesreport@dolisalesreport"));
if (!$user->admin) accessforbidden();

llxHeader('', $langs->trans("DoliSalesReportAbout"));

$head = dolisalesreportAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("ModuleDoliSalesReportName"), -1, "dolisalesreport@dolisalesreport");

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<h3>'.$langs->trans("ModuleDoliSalesReportName").'</h3>';
print '<p>'.$langs->trans("ModuleDoliSalesReportDesc").'</p>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("Version").'</td><td>1.0.0</td></tr>';
print '<tr><td>'.$langs->trans("Author").'</td><td>Ignacio Florido</td></tr>';
print '<tr><td>Web</td><td><a href="https://cv.iflorido.es" target="_blank">cv.iflorido.es</a></td></tr>';
print '<tr><td>'.$langs->trans("Email").'</td><td>iflorido@gmail.com</td></tr>';
print '</table>';
print '</div></div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
