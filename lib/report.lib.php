<?php
/**
 * \file    dolisalesreport/lib/report.lib.php
 * \ingroup dolisalesreport
 * \brief   Motor de consultas de ventas a partir de la facturacion.
 *
 * Lee de llx_facture + llx_facturedet. Como el modulo TakePos (TPV) genera
 * facturas reales en Dolibarr, leyendo de aqui capturamos tanto facturas
 * normales como tickets de TPV en un unico sitio.
 *
 * Las lineas con fk_product = 0 son texto libre (habitual en tickets de TPV):
 * no se pueden asociar a un producto real, asi que se agrupan todas bajo un
 * unico pseudo-producto "Productos sin referencia (texto libre)".
 */

// Constante interna para identificar el grupo de texto libre.
if (!defined('DOLISALESREPORT_FREETEXT_KEY')) {
	define('DOLISALESREPORT_FREETEXT_KEY', 'FREETEXT');
}

/**
 * Construye la clausula WHERE comun a partir de los filtros.
 *
 * @param DoliDB $db          Handler BD
 * @param int    $date_start  Timestamp inicio (incluido)
 * @param int    $date_end    Timestamp fin (incluido, se ajusta a fin de dia)
 * @param int    $socid       Id de tercero (0 = todos)
 * @param int    $productid   Id de producto (0 = todos)
 * @param int    $entity      Entidad
 * @return string             Clausula WHERE (sin la palabra WHERE)
 */
function dolisalesreport_build_where($db, $date_start, $date_end, $socid, $productid, $entity)
{
	// Solo facturas validadas o pagadas (fk_statut >= 1). Excluye borradores (0).
	$where = " f.entity IN (".getEntity('invoice').")";
	$where .= " AND f.fk_statut >= 1";
	// Excluimos facturas de tipo abono/anticipo? Mantenemos estandar (0) y replacement (3).
	// type: 0=standard, 1=replacement, 2=credit note, 3=deposit, 4=proforma
	$where .= " AND f.type IN (0, 1, 3)";

	if (!empty($date_start)) {
		$where .= " AND f.datef >= '".$db->idate($date_start)."'";
	}
	if (!empty($date_end)) {
		// Ajustamos al final del dia indicado
		$end_of_day = dol_get_last_hour($date_end);
		$where .= " AND f.datef <= '".$db->idate($end_of_day)."'";
	}
	if ($socid > 0) {
		$where .= " AND f.fk_soc = ".((int) $socid);
	}
	if ($productid > 0) {
		$where .= " AND fd.fk_product = ".((int) $productid);
	}

	return $where;
}

/**
 * Devuelve el numero total de filas (productos distintos) que produce el informe.
 * Necesario para la paginacion.
 *
 * @return int
 */
function dolisalesreport_count_products($db, $date_start, $date_end, $socid, $productid, $entity)
{
	$where = dolisalesreport_build_where($db, $date_start, $date_end, $socid, $productid, $entity);

	// Contamos grupos distintos: cada producto real + un unico grupo para texto libre.
	$sql = "SELECT COUNT(*) as nb FROM (";
	$sql .= " SELECT CASE WHEN fd.fk_product > 0 THEN CAST(fd.fk_product AS CHAR) ELSE '".DOLISALESREPORT_FREETEXT_KEY."' END as grp";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facturedet as fd ON fd.fk_facture = f.rowid";
	$sql .= " WHERE ".$where;
	// Solo lineas de producto/servicio o texto libre. Excluimos subtotales/saltos.
	$sql .= " AND (fd.product_type IN (0,1) OR fd.fk_product = 0)";
	$sql .= " AND fd.fk_code_ventilation >= 0"; // sanity, evita lineas especiales negativas
	$sql .= " GROUP BY grp";
	$sql .= ") as sub";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog("dolisalesreport_count_products error: ".$db->lasterror(), LOG_ERR);
		return 0;
	}
	$obj = $db->fetch_object($resql);
	return $obj ? (int) $obj->nb : 0;
}

/**
 * Devuelve el informe agregado de ventas por producto.
 *
 * Cada fila: ref, label, product_id, qty_total, total_ht, total_ttc, nb_invoices.
 * Las lineas de texto libre se agrupan en una sola fila.
 *
 * @param DoliDB $db
 * @param int    $date_start
 * @param int    $date_end
 * @param int    $socid
 * @param int    $productid
 * @param int    $entity
 * @param string $sortfield   Campo de orden (whitelist)
 * @param string $sortorder   ASC|DESC
 * @param int    $limit       0 = sin limite (para exportacion / dashboard)
 * @param int    $offset
 * @return array              Lista de filas (array asociativo)
 */
function dolisalesreport_get_products_report($db, $date_start, $date_end, $socid, $productid, $entity, $sortfield = 'total_ht', $sortorder = 'DESC', $limit = 0, $offset = 0)
{
	$where = dolisalesreport_build_where($db, $date_start, $date_end, $socid, $productid, $entity);

	// Whitelist de ordenacion para evitar inyeccion
	$allowed_sort = array('ref', 'label', 'qty_total', 'total_ht', 'total_ttc', 'nb_invoices');
	if (!in_array($sortfield, $allowed_sort)) {
		$sortfield = 'total_ht';
	}
	$sortorder = (strtoupper($sortorder) == 'ASC') ? 'ASC' : 'DESC';

	$sql = "SELECT";
	$sql .= " CASE WHEN fd.fk_product > 0 THEN fd.fk_product ELSE 0 END as product_id,";
	$sql .= " CASE WHEN fd.fk_product > 0 THEN p.ref ELSE '' END as ref,";
	$sql .= " CASE WHEN fd.fk_product > 0 THEN p.label ELSE '".$db->escape($GLOBALS['langs']->transnoentitiesnoconv("FreeTextProductLabel"))."' END as label,";
	$sql .= " SUM(fd.qty) as qty_total,";
	$sql .= " SUM(fd.total_ht) as total_ht,";
	$sql .= " SUM(fd.total_ttc) as total_ttc,";
	$sql .= " COUNT(DISTINCT f.rowid) as nb_invoices,";
	$sql .= " CASE WHEN fd.fk_product > 0 THEN CAST(fd.fk_product AS CHAR) ELSE '".DOLISALESREPORT_FREETEXT_KEY."' END as grp";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facturedet as fd ON fd.fk_facture = f.rowid";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = fd.fk_product";
	$sql .= " WHERE ".$where;
	$sql .= " AND (fd.product_type IN (0,1) OR fd.fk_product = 0)";
	$sql .= " GROUP BY grp";
	$sql .= " ORDER BY ".$sortfield." ".$sortorder;

	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog("dolisalesreport_get_products_report error: ".$db->lasterror(), LOG_ERR);
		return array();
	}

	$rows = array();
	while ($obj = $db->fetch_object($resql)) {
		$is_free = ($obj->grp === DOLISALESREPORT_FREETEXT_KEY);
		$rows[] = array(
			'product_id' => (int) $obj->product_id,
			'ref'        => $is_free ? '' : $obj->ref,
			'label'      => $obj->label,
			'qty_total'  => (float) $obj->qty_total,
			'total_ht'   => (float) $obj->total_ht,
			'total_ttc'  => (float) $obj->total_ttc,
			'nb_invoices' => (int) $obj->nb_invoices,
			'is_free'    => $is_free,
		);
	}
	$db->free($resql);

	return $rows;
}

/**
 * Totales globales del informe (para cabeceras / dashboard KPI).
 *
 * @return array  array('total_ht'=>, 'total_ttc'=>, 'qty_total'=>, 'nb_invoices'=>, 'nb_products'=>)
 */
function dolisalesreport_get_totals($db, $date_start, $date_end, $socid, $productid, $entity)
{
	$where = dolisalesreport_build_where($db, $date_start, $date_end, $socid, $productid, $entity);

	$sql = "SELECT";
	$sql .= " SUM(fd.total_ht) as total_ht,";
	$sql .= " SUM(fd.total_ttc) as total_ttc,";
	$sql .= " SUM(fd.qty) as qty_total,";
	$sql .= " COUNT(DISTINCT f.rowid) as nb_invoices";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facturedet as fd ON fd.fk_facture = f.rowid";
	$sql .= " WHERE ".$where;
	$sql .= " AND (fd.product_type IN (0,1) OR fd.fk_product = 0)";

	$resql = $db->query($sql);
	$out = array('total_ht' => 0, 'total_ttc' => 0, 'qty_total' => 0, 'nb_invoices' => 0, 'nb_products' => 0);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$out['total_ht'] = (float) $obj->total_ht;
		$out['total_ttc'] = (float) $obj->total_ttc;
		$out['qty_total'] = (float) $obj->qty_total;
		$out['nb_invoices'] = (int) $obj->nb_invoices;
	}
	$out['nb_products'] = dolisalesreport_count_products($db, $date_start, $date_end, $socid, $productid, $entity);

	return $out;
}

/**
 * Ventas agregadas por mes (para grafica de evolucion del dashboard).
 *
 * @return array  array de array('ym'=>'YYYY-MM', 'total_ht'=>, 'total_ttc'=>)
 */
function dolisalesreport_get_monthly_series($db, $date_start, $date_end, $socid, $productid, $entity)
{
	$where = dolisalesreport_build_where($db, $date_start, $date_end, $socid, $productid, $entity);

	$sql = "SELECT DATE_FORMAT(f.datef, '%Y-%m') as ym,";
	$sql .= " SUM(fd.total_ht) as total_ht, SUM(fd.total_ttc) as total_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facturedet as fd ON fd.fk_facture = f.rowid";
	$sql .= " WHERE ".$where;
	$sql .= " AND (fd.product_type IN (0,1) OR fd.fk_product = 0)";
	$sql .= " GROUP BY ym ORDER BY ym ASC";

	$resql = $db->query($sql);
	$rows = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rows[] = array(
				'ym' => $obj->ym,
				'total_ht' => (float) $obj->total_ht,
				'total_ttc' => (float) $obj->total_ttc,
			);
		}
	}
	return $rows;
}
