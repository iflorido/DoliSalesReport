<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es> */

/**
 * \file    dolisalesreport/report_products.php
 * \ingroup dolisalesreport
 * \brief   Informe de ventas por producto a partir de la facturacion.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Error: No se pudo cargar main.inc.php");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

dol_include_once('/dolisalesreport/lib/dolisalesreport.lib.php');
dol_include_once('/dolisalesreport/lib/report.lib.php');
dol_include_once('/dolisalesreport/core/lib/licence.lib.php');

$langs->loadLangs(array("dolisalesreport@dolisalesreport", "bills", "products", "companies"));

if (empty($user->rights->dolisalesreport->report->read)) accessforbidden();

/* --- LICENCIA --- */
$status = function_exists('dolisalesreport_get_license_status') ? dolisalesreport_get_license_status(false) : 'not_checked';
$is_license_valid = ($status == 'valid');

/* --- PARAMETROS --- */
$action    = GETPOST('action', 'aZ09');
$productid = GETPOSTINT('productid');
$socid     = GETPOSTINT('socid');

$date_startday   = GETPOSTINT('date_startday');
$date_startmonth = GETPOSTINT('date_startmonth');
$date_startyear  = GETPOSTINT('date_startyear');
$date_endday     = GETPOSTINT('date_endday');
$date_endmonth   = GETPOSTINT('date_endmonth');
$date_endyear    = GETPOSTINT('date_endyear');

if ($date_startmonth) {
	$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
} else {
	$date_start = dol_get_first_day((int) dol_print_date(dol_now(), '%Y'), 1);
}
if ($date_endmonth) {
	$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);
} else {
	$date_end = dol_now();
}

$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
if (empty($sortfield)) $sortfield = 'total_ht';
if (empty($sortorder)) $sortorder = 'DESC';

$default_limit = getDolGlobalInt('DOLISALESREPORT_DEFAULT_LIMIT', 25);
$limit = GETPOSTINT('limit');
if (empty($limit)) $limit = $default_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

$entity = $conf->entity;

// Parametros de filtros (SIN action, para no colisionar entre search y export).
$param_filters  = '&productid='.((int) $productid).'&socid='.((int) $socid);
$param_filters .= '&date_startday='.((int) dol_print_date($date_start, '%d'));
$param_filters .= '&date_startmonth='.((int) dol_print_date($date_start, '%m'));
$param_filters .= '&date_startyear='.((int) dol_print_date($date_start, '%Y'));
$param_filters .= '&date_endday='.((int) dol_print_date($date_end, '%d'));
$param_filters .= '&date_endmonth='.((int) dol_print_date($date_end, '%m'));
$param_filters .= '&date_endyear='.((int) dol_print_date($date_end, '%Y'));
if ($limit > 0 && $limit != $default_limit) $param_filters .= '&limit='.((int) $limit);

// Para la lista/paginacion (orden, titulos): mantiene action=search.
$param = $param_filters.'&action=search';

/* === EXPORTACION A EXCEL (antes de cualquier salida HTML) === */
if ($action == 'export_xlsx' && $is_license_valid) {
	if (empty($user->rights->dolisalesreport->report->export)) accessforbidden();

	// Diagnostico opcional: anade &debug=1 a la URL para ver el estado de PhpSpreadsheet.
	if (GETPOSTINT('debug') == 1) {
		header('Content-Type: text/plain; charset=UTF-8');
		print "== Diagnostico PhpSpreadsheet ==\n";
		print "DOL_DOCUMENT_ROOT = ".DOL_DOCUMENT_ROOT."\n";
		print "PHPEXCELNEW_PATH definida = ".(defined('PHPEXCELNEW_PATH') ? PHPEXCELNEW_PATH : 'NO')."\n\n";
		$a1 = DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
		$a2 = DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
		print "autoloader.php existe = ".(file_exists($a1) ? 'SI' : 'NO')." ($a1)\n";
		print "Psr/autoloader.php existe = ".(file_exists($a2) ? 'SI' : 'NO')." ($a2)\n";
		if (defined('PHPEXCELNEW_PATH')) {
			print "Spreadsheet.php existe = ".(file_exists(PHPEXCELNEW_PATH.'Spreadsheet.php') ? 'SI' : 'NO')." (".PHPEXCELNEW_PATH."Spreadsheet.php)\n";
		}
		// Intento de carga
		if (file_exists($a1)) {
			require_once $a1;
			if (file_exists($a2)) require_once $a2;
			if (defined('PHPEXCELNEW_PATH') && file_exists(PHPEXCELNEW_PATH.'Spreadsheet.php')) require_once PHPEXCELNEW_PATH.'Spreadsheet.php';
		}
		print "\nClase Spreadsheet cargada = ".(class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? 'SI' : 'NO')."\n";
		print "Clase Xlsx writer cargada = ".(class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx') ? 'SI' : 'NO')."\n";
		print "Extension ZipArchive = ".(class_exists('ZipArchive') ? 'SI' : 'NO')."\n";
		print "Filas a exportar = ".count(dolisalesreport_get_products_report($db, $date_start, $date_end, $socid, $productid, $entity, $sortfield, $sortorder, 0, 0))."\n";
		exit;
	}

	$allrows = dolisalesreport_get_products_report($db, $date_start, $date_end, $socid, $productid, $entity, $sortfield, $sortorder, 0, 0);
	$totals  = dolisalesreport_get_totals($db, $date_start, $date_end, $socid, $productid, $entity);

	$client_name = $langs->transnoentities("AllThirdParties");
	if ($socid > 0) {
		$tmpsoc = new Societe($db);
		$tmpsoc->fetch($socid);
		$client_name = $tmpsoc->name;
	}

	dol_include_once('/dolisalesreport/lib/export_xlsx.lib.php');
	dolisalesreport_export_products_xlsx($allrows, $totals, $date_start, $date_end, $client_name, $langs);
	exit;
}

/* === VISTA === */
$form = new Form($db);

llxHeader('', $langs->trans("ProductSalesReport"), '', '', 0, 0, '', '', '', 'mod-dolisalesreport page-report_products');

print load_fiche_titre($langs->trans("ProductSalesReport"), '', 'dolisalesreport@dolisalesreport');

if (!$is_license_valid) {
	dolisalesreport_print_license_banner($status);
}

// UN UNICO formulario GET: filtros + lista + selector de limite coordinados.
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" name="reportform" id="reportform">';
print '<input type="hidden" name="action" value="search">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent">';

print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Product").'</td><td>';
$form->select_produits($productid, 'productid', '', 0, 0, -1, 2, '', 1, array(), 0, '1', 0, 'minwidth300', 0, $langs->trans("AllProducts"));
print '</td></tr>';

print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
$form->select_company($socid, 'socid', '', $langs->trans("AllThirdParties"), 0, 0, array(), 0, 'minwidth300');
print '</td></tr>';

print '<tr><td>'.$langs->trans("DateStart").'</td><td>';
print $form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0);
print '</td></tr>';
print '<tr><td>'.$langs->trans("DateEnd").'</td><td>';
print $form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0);
print '</td></tr>';

print '</table>';
print '</div>';

print '<div class="center" style="margin: 15px 0;">';
print '<input type="submit" name="button_search" class="button button-save" value="'.$langs->trans("GenerateReport").'"'.($is_license_valid ? '' : ' disabled="disabled"').'>';
print '</div>';

if (($action == 'search' || $action == '') && $is_license_valid) {

	$nbtotal = dolisalesreport_count_products($db, $date_start, $date_end, $socid, $productid, $entity);
	$rows    = dolisalesreport_get_products_report($db, $date_start, $date_end, $socid, $productid, $entity, $sortfield, $sortorder, $limit, $offset);
	$totals  = dolisalesreport_get_totals($db, $date_start, $date_end, $socid, $productid, $entity);

	$exporturl = $_SERVER["PHP_SELF"].'?action=export_xlsx'.$param_filters.'&sortfield='.$sortfield.'&sortorder='.$sortorder.'&token='.newToken();
	$morehtmlright = '';
	if (!empty($user->rights->dolisalesreport->report->export)) {
		$morehtmlright = '<a class="butAction" href="'.$exporturl.'"><span class="fa fa-file-excel paddingright"></span>'.$langs->trans("ExportToExcel").'</a>';
	}

	$rangetext = dol_print_date($date_start, 'day').' &rarr; '.dol_print_date($date_end, 'day');

	print '<br>';
	print_barre_liste($langs->trans("ResultsForRange", $rangetext), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $morehtmlright, $nbtotal, $nbtotal, 'dolisalesreport@dolisalesreport', 0, '', '', $limit, 0, 0, 1);

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("DistinctProducts").'</td>';
	print '<td class="right">'.$langs->trans("TotalQtySold").'</td>';
	print '<td class="right">'.$langs->trans("InvoicesCount").'</td>';
	print '<td class="right">'.$langs->trans("TotalHT").'</td>';
	print '<td class="right">'.$langs->trans("TotalTTC").'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td><strong>'.$totals['nb_products'].'</strong></td>';
	print '<td class="right">'.price2num($totals['qty_total'], 'MS').'</td>';
	print '<td class="right">'.$totals['nb_invoices'].'</td>';
	print '<td class="right amount"><strong>'.price($totals['total_ht']).'</strong></td>';
	print '<td class="right amount"><strong>'.price($totals['total_ttc']).'</strong></td>';
	print '</tr>';
	print '</table>';
	print '</div><br>';

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste centpercent">';

	print '<tr class="liste_titre">';
	print getTitleFieldOfList($langs->trans("Ref"), 0, $_SERVER["PHP_SELF"], "ref", "", $param, "", $sortfield, $sortorder);
	print getTitleFieldOfList($langs->trans("ProductLabel"), 0, $_SERVER["PHP_SELF"], "label", "", $param, "", $sortfield, $sortorder);
	print getTitleFieldOfList($langs->trans("QtySold"), 0, $_SERVER["PHP_SELF"], "qty_total", "", $param, 'class="right"', $sortfield, $sortorder);
	print getTitleFieldOfList($langs->trans("InvoicesCount"), 0, $_SERVER["PHP_SELF"], "nb_invoices", "", $param, 'class="right"', $sortfield, $sortorder);
	print getTitleFieldOfList($langs->trans("TotalHT"), 0, $_SERVER["PHP_SELF"], "total_ht", "", $param, 'class="right"', $sortfield, $sortorder);
	print getTitleFieldOfList($langs->trans("TotalTTC"), 0, $_SERVER["PHP_SELF"], "total_ttc", "", $param, 'class="right"', $sortfield, $sortorder);
	print '</tr>';

	if (count($rows) == 0) {
		print '<tr class="oddeven"><td colspan="6" class="opacitymedium center">'.$langs->trans("NoSalesFound").'</td></tr>';
	} else {
		foreach ($rows as $row) {
			print '<tr class="oddeven">';
			if ($row['is_free']) {
				print '<td><span class="opacitymedium">-</span></td>';
				print '<td><em>'.dol_escape_htmltag($row['label']).'</em> <span class="badge badge-secondary" title="'.$langs->trans("FreeTextHelp").'">'.$langs->trans("FreeText").'</span></td>';
			} else {
				$produrl = DOL_URL_ROOT.'/product/card.php?id='.$row['product_id'];
				print '<td><a href="'.$produrl.'">'.dol_escape_htmltag($row['ref']).'</a></td>';
				print '<td>'.dol_escape_htmltag($row['label']).'</td>';
			}
			print '<td class="right">'.price2num($row['qty_total'], 'MS').'</td>';
			print '<td class="right">'.$row['nb_invoices'].'</td>';
			print '<td class="right amount">'.price($row['total_ht']).'</td>';
			print '<td class="right amount">'.price($row['total_ttc']).'</td>';
			print '</tr>';
		}
	}

	print '</table>';
	print '</div>';
}

print '</form>';

llxFooter();
$db->close();
