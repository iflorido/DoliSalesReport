<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es> */

/**
 * \file    dolisalesreport/index.php
 * \ingroup dolisalesreport
 * \brief   Dashboard del modulo DoliSalesReport.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Error: No se pudo cargar main.inc.php");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

dol_include_once('/dolisalesreport/lib/dolisalesreport.lib.php');
dol_include_once('/dolisalesreport/lib/report.lib.php');
dol_include_once('/dolisalesreport/core/lib/licence.lib.php');

$langs->loadLangs(array("dolisalesreport@dolisalesreport", "bills", "products"));

if (empty($user->rights->dolisalesreport->report->read)) accessforbidden();

/* --- LICENCIA --- */
$status = function_exists('dolisalesreport_get_license_status') ? dolisalesreport_get_license_status(false) : 'not_checked';
$is_license_valid = ($status == 'valid');

/* --- FECHAS (por defecto anio en curso) --- */
$date_startmonth = GETPOSTINT('date_startmonth');
$date_startday   = GETPOSTINT('date_startday');
$date_startyear  = GETPOSTINT('date_startyear');
$date_endmonth   = GETPOSTINT('date_endmonth');
$date_endday     = GETPOSTINT('date_endday');
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

$entity = $conf->entity;
$form = new Form($db);

llxHeader('', $langs->trans("SalesDashboard"), '', '', 0, 0, '', '', '', 'mod-dolisalesreport page-index');

print load_fiche_titre($langs->trans("SalesDashboard"), '', 'dolisalesreport@dolisalesreport');

if (!$is_license_valid) {
	dolisalesreport_print_license_banner($status);
	llxFooter();
	$db->close();
	exit;
}

// --- Filtro de fechas ---
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" name="dashform">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive-no-min"><table class="border centpercent"><tr>';
print '<td class="titlefield">'.$langs->trans("DateStart").'</td><td>'.$form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0).'</td>';
print '<td>'.$langs->trans("DateEnd").'</td><td>'.$form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0).'</td>';
print '<td><input type="submit" class="button button-save" value="'.$langs->trans("Refresh").'"></td>';
print '</tr></table></div>';
print '</form><br>';

// --- KPIs ---
$totals = dolisalesreport_get_totals($db, $date_start, $date_end, 0, 0, $entity);

print '<div class="fichecenter"><div class="fichethirdleft">';
print '<div class="box-flex-container">';

$boxes = array(
	array('label' => $langs->trans("TotalHT"),         'val' => price($totals['total_ht']),  'icon' => 'fa-euro-sign'),
	array('label' => $langs->trans("TotalTTC"),        'val' => price($totals['total_ttc']), 'icon' => 'fa-receipt'),
	array('label' => $langs->trans("DistinctProducts"),'val' => $totals['nb_products'],      'icon' => 'fa-box'),
	array('label' => $langs->trans("InvoicesCount"),   'val' => $totals['nb_invoices'],      'icon' => 'fa-file-invoice'),
	array('label' => $langs->trans("TotalQtySold"),    'val' => price2num($totals['qty_total'], 'MS'), 'icon' => 'fa-cubes'),
);
print '</div></div></div>';

print '<div class="dsr-kpi-grid">';
foreach ($boxes as $b) {
	print '<div class="dsr-kpi-card">';
	print '<span class="fa '.$b['icon'].' dsr-kpi-icon"></span>';
	print '<div class="dsr-kpi-value">'.$b['val'].'</div>';
	print '<div class="dsr-kpi-label">'.$b['label'].'</div>';
	print '</div>';
}
print '</div><br>';

// --- GRAFICA 1: Top 10 productos por importe (HT) ---
$allrows = dolisalesreport_get_products_report($db, $date_start, $date_end, 0, 0, $entity, 'total_ht', 'DESC', 0, 0);

$top = array_slice($allrows, 0, 10);
$datatop = array();
foreach ($top as $r) {
	$lbl = $r['is_free'] ? $langs->trans("FreeText") : ($r['ref'] ? $r['ref'] : $r['label']);
	$lbl = dol_trunc($lbl, 20);
	$datatop[] = array($lbl, round($r['total_ht'], 2));
}

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans("TopProductsByAmount").'</td></tr><tr><td class="center">';
if (count($datatop) > 0) {
	$px1 = new DolGraph();
	$px1->SetData($datatop);
	$px1->SetType(array('bars'));
	$px1->setShowLegend(0);
	$px1->SetWidth(500);
	$px1->SetHeight(320);
	$px1->setShowPercent(0);
	$px1->draw('graph_topproducts');
	print $px1->show();
} else {
	print '<span class="opacitymedium">'.$langs->trans("NoSalesFound").'</span>';
}
print '</td></tr></table></div>';
print '</div>';

// --- GRAFICA 2: Evolucion mensual (HT) ---
$series = dolisalesreport_get_monthly_series($db, $date_start, $date_end, 0, 0, $entity);
$datamonth = array();
foreach ($series as $s) {
	$datamonth[] = array($s['ym'], round($s['total_ht'], 2));
}

print '<div class="fichehalfright">';
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans("MonthlyEvolution").'</td></tr><tr><td class="center">';
if (count($datamonth) > 0) {
	$px2 = new DolGraph();
	$px2->SetData($datamonth);
	$px2->SetType(array('lines'));
	$px2->setShowLegend(0);
	$px2->SetWidth(500);
	$px2->SetHeight(320);
	$px2->draw('graph_monthly');
	print $px2->show();
} else {
	print '<span class="opacitymedium">'.$langs->trans("NoSalesFound").'</span>';
}
print '</td></tr></table></div>';
print '</div></div>';

// --- Top y bottom productos en tablas rapidas ---
print '<div class="clearboth"></div><br>';
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("MostSoldProducts").'</td></tr>';
$mostsold = $allrows;
usort($mostsold, function ($a, $b) {
	if ($a['qty_total'] == $b['qty_total']) return 0;
	return ($a['qty_total'] < $b['qty_total']) ? 1 : -1;
});
foreach (array_slice($mostsold, 0, 5) as $r) {
	$name = $r['is_free'] ? $r['label'] : ($r['ref'].' - '.$r['label']);
	print '<tr class="oddeven"><td>'.dol_escape_htmltag(dol_trunc($name, 40)).'</td><td class="right">'.price2num($r['qty_total'], 'MS').'</td></tr>';
}
print '</table></div></div>';

print '<div class="fichehalfright">';
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("LeastSoldProducts").'</td></tr>';
$leastsold = $allrows;
usort($leastsold, function ($a, $b) {
	if ($a['qty_total'] == $b['qty_total']) return 0;
	return ($a['qty_total'] > $b['qty_total']) ? 1 : -1;
});
foreach (array_slice($leastsold, 0, 5) as $r) {
	$name = $r['is_free'] ? $r['label'] : ($r['ref'].' - '.$r['label']);
	print '<tr class="oddeven"><td>'.dol_escape_htmltag(dol_trunc($name, 40)).'</td><td class="right">'.price2num($r['qty_total'], 'MS').'</td></tr>';
}
print '</table></div></div></div>';

print '<div class="clearboth"></div><br>';
print '<div class="center"><a class="button" href="'.dol_buildpath('/dolisalesreport/report_products.php', 1).'">'.$langs->trans("GoToProductReport").'</a></div>';

llxFooter();
$db->close();
