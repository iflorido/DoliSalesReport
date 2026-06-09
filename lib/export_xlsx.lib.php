<?php
/**
 * \file    dolisalesreport/lib/export_xlsx.lib.php
 * \ingroup dolisalesreport
 * \brief   Exportacion del informe de productos a Excel (PhpSpreadsheet).
 *
 * Dolibarr incluye PhpSpreadsheet en /includes/phpoffice/. Si por alguna razon
 * no estuviera disponible, hacemos fallback a CSV para no dejar al usuario sin
 * exportacion.
 */

/**
 * Genera y envia un fichero Excel con el informe de productos.
 * Envia los headers HTTP y termina la salida (el llamante debe hacer exit).
 *
 * @param array  $rows         Filas del informe
 * @param array  $totals       Totales globales
 * @param int    $date_start   Timestamp inicio
 * @param int    $date_end     Timestamp fin
 * @param string $client_name  Nombre del cliente (o "Todos")
 * @param Translate $langs     Objeto de traduccion
 * @return void
 */
function dolisalesreport_export_products_xlsx($rows, $totals, $date_start, $date_end, $client_name, $langs)
{
	$datestr = dol_print_date(dol_now(), '%Y%m%d_%H%M');
	$filename = 'informe_ventas_productos_'.$datestr;

	// Carga de PhpSpreadsheet tal y como lo hace Dolibarr internamente.
	// Dolibarr define PHPEXCELNEW_PATH = .../includes/phpoffice/phpspreadsheet/src/PhpSpreadsheet/
	// y NO incluye un autoload.php; registramos un autoloader propio sobre esa ruta.
	$has_pss = false;
	if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
		$base = defined('PHPEXCELNEW_PATH') ? PHPEXCELNEW_PATH : (DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/PhpSpreadsheet/');
		// base apunta a .../src/PhpSpreadsheet/ ; el prefijo de namespace cuelga de ahi.
		if (is_dir($base)) {
			spl_autoload_register(function ($class) use ($base) {
				$prefix = 'PhpOffice\\PhpSpreadsheet\\';
				$len = strlen($prefix);
				if (strncmp($prefix, $class, $len) !== 0) return;
				$relative = substr($class, $len);
				$file = rtrim($base, '/').'/'.str_replace('\\', '/', $relative).'.php';
				if (file_exists($file)) require $file;
			});
			// Dependencia Psr\SimpleCache que PhpSpreadsheet necesita.
			$simplecache = dirname(rtrim($base, '/'), 2).'/simple-cache/';
			if (is_dir($simplecache)) {
				spl_autoload_register(function ($class) use ($simplecache) {
					$prefix = 'Psr\\SimpleCache\\';
					$len = strlen($prefix);
					if (strncmp($prefix, $class, $len) !== 0) return;
					$relative = substr($class, $len);
					$file = rtrim($simplecache, '/').'/'.str_replace('\\', '/', $relative).'.php';
					if (file_exists($file)) require $file;
				});
			}
		}
	}
	$has_pss = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');

	if (!$has_pss) {
		// Si no se pudo cargar, exportamos en CSV para no dejar al usuario sin nada.
		dolisalesreport_export_products_csv($rows, $totals, $date_start, $date_end, $client_name, $langs, $filename);
		return;
	}

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle($langs->transnoentities("ProductSalesReport"));

	// --- Cabecera del informe ---
	$sheet->setCellValue('A1', $langs->transnoentities("ProductSalesReport"));
	$sheet->mergeCells('A1:F1');
	$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

	$range = dol_print_date($date_start, '%d/%m/%Y').' - '.dol_print_date($date_end, '%d/%m/%Y');
	$sheet->setCellValue('A2', $langs->transnoentities("Period").': '.$range);
	$sheet->mergeCells('A2:F2');
	$sheet->setCellValue('A3', $langs->transnoentities("ThirdParty").': '.$client_name);
	$sheet->mergeCells('A3:F3');
	$sheet->setCellValue('A4', $langs->transnoentities("GeneratedOn").': '.dol_print_date(dol_now(), '%d/%m/%Y %H:%M'));
	$sheet->mergeCells('A4:F4');

	// --- Cabecera de tabla (fila 6) ---
	$headerRow = 6;
	$headers = array(
		'A' => $langs->transnoentities("Ref"),
		'B' => $langs->transnoentities("ProductLabel"),
		'C' => $langs->transnoentities("QtySold"),
		'D' => $langs->transnoentities("InvoicesCount"),
		'E' => $langs->transnoentities("TotalHT"),
		'F' => $langs->transnoentities("TotalTTC"),
	);
	foreach ($headers as $col => $label) {
		$sheet->setCellValue($col.$headerRow, $label);
	}
	$sheet->getStyle('A'.$headerRow.':F'.$headerRow)->getFont()->setBold(true);
	$sheet->getStyle('A'.$headerRow.':F'.$headerRow)->getFill()
		->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
		->getStartColor()->setRGB('2F5496');
	$sheet->getStyle('A'.$headerRow.':F'.$headerRow)->getFont()->getColor()->setRGB('FFFFFF');

	// --- Datos ---
	$row = $headerRow + 1;
	foreach ($rows as $r) {
		$sheet->setCellValue('A'.$row, $r['is_free'] ? '-' : $r['ref']);
		$sheet->setCellValue('B'.$row, $r['label']);
		$sheet->setCellValueExplicit('C'.$row, $r['qty_total'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
		$sheet->setCellValueExplicit('D'.$row, $r['nb_invoices'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
		$sheet->setCellValueExplicit('E'.$row, round($r['total_ht'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
		$sheet->setCellValueExplicit('F'.$row, round($r['total_ttc'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
		$row++;
	}

	// --- Fila de totales ---
	$sheet->setCellValue('A'.$row, $langs->transnoentities("Total"));
	$sheet->mergeCells('A'.$row.':B'.$row);
	$sheet->setCellValueExplicit('C'.$row, $totals['qty_total'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
	$sheet->setCellValueExplicit('D'.$row, $totals['nb_invoices'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
	$sheet->setCellValueExplicit('E'.$row, round($totals['total_ht'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
	$sheet->setCellValueExplicit('F'.$row, round($totals['total_ttc'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
	$sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
	$sheet->getStyle('A'.$row.':F'.$row)->getFill()
		->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
		->getStartColor()->setRGB('D9E1F2');

	// Formato numerico de moneda en columnas E y F
	$lastDataRow = $row;
	$sheet->getStyle('E7:F'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');
	$sheet->getStyle('C7:C'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0.###');

	// Anchos de columna
	$sheet->getColumnDimension('A')->setWidth(18);
	$sheet->getColumnDimension('B')->setWidth(45);
	$sheet->getColumnDimension('C')->setWidth(14);
	$sheet->getColumnDimension('D')->setWidth(14);
	$sheet->getColumnDimension('E')->setWidth(16);
	$sheet->getColumnDimension('F')->setWidth(16);

	// Bordes en toda la tabla
	$sheet->getStyle('A'.$headerRow.':F'.$lastDataRow)->getBorders()->getAllBorders()
		->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

	// Congelar cabecera
	$sheet->freezePane('A'.($headerRow + 1));

	// --- Envio ---
	// Limpiamos cualquier salida previa (warnings, BOM, etc.) para no corromper el fichero.
	if (ob_get_level()) {
		while (ob_get_level()) { ob_end_clean(); }
	}

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
	header('Cache-Control: max-age=0');
	header('Pragma: public');

	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
}

/**
 * Fallback CSV si no hay PhpSpreadsheet disponible.
 */
function dolisalesreport_export_products_csv($rows, $totals, $date_start, $date_end, $client_name, $langs, $filename)
{
	if (ob_get_level()) {
		while (ob_get_level()) { ob_end_clean(); }
	}
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment;filename="'.$filename.'.csv"');
	$out = fopen('php://output', 'w');
	fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel

	fputcsv($out, array($langs->transnoentities("ProductSalesReport")), ';');
	fputcsv($out, array($langs->transnoentities("Period"), dol_print_date($date_start, '%d/%m/%Y').' - '.dol_print_date($date_end, '%d/%m/%Y')), ';');
	fputcsv($out, array($langs->transnoentities("ThirdParty"), $client_name), ';');
	fputcsv($out, array(), ';');

	fputcsv($out, array(
		$langs->transnoentities("Ref"),
		$langs->transnoentities("ProductLabel"),
		$langs->transnoentities("QtySold"),
		$langs->transnoentities("InvoicesCount"),
		$langs->transnoentities("TotalHT"),
		$langs->transnoentities("TotalTTC"),
	), ';');

	foreach ($rows as $r) {
		fputcsv($out, array(
			$r['is_free'] ? '-' : $r['ref'],
			$r['label'],
			$r['qty_total'],
			$r['nb_invoices'],
			round($r['total_ht'], 2),
			round($r['total_ttc'], 2),
		), ';');
	}

	fputcsv($out, array(
		$langs->transnoentities("Total"), '',
		$totals['qty_total'], $totals['nb_invoices'],
		round($totals['total_ht'], 2), round($totals['total_ttc'], 2),
	), ';');

	fclose($out);
}
