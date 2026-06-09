<?php
/*
 * Libreria de validacion de licencia para DoliSalesReport.
 * Mismo sistema y endpoint remoto que DoliWooSync.
 * IMPORTANTE: el endpoint checklicence.php debe tener dado de alta
 * el module_slug 'dolisalesreport'.
 */

/**
 * Devuelve el estado de la licencia usando cache (constante en BD).
 * Solo re-consulta al servidor remoto si se fuerza o si caduco el intervalo.
 *
 * @param bool $force_remote_check  Forzar comprobacion remota
 * @return string                   valid|invalid|expired|tampered|url_mismatch|invalid_key|connection_error|...
 */
function dolisalesreport_get_license_status($force_remote_check = false)
{
	global $conf, $db;

	$current_status = getDolGlobalString('DOLISALESREPORT_LICENSE_STATUS', 'not_checked');
	$last_check_time = (int) getDolGlobalString('DOLISALESREPORT_LICENSE_LAST_CHECK', 0);
	$check_interval = 86400; // 24 horas

	if ($force_remote_check || $current_status == 'not_checked' || (time() - $last_check_time) > $check_interval) {
		$license_key = getDolGlobalString('DOLISALESREPORT_LICENSE_KEY');

		if (empty($license_key)) {
			dolibarr_set_const($db, "DOLISALESREPORT_LICENSE_STATUS", 'invalid', 'chaine', 0, '', $conf->entity);
			return 'invalid';
		}

		return dolisalesreport_check_remote_license();
	}

	return $current_status;
}

/**
 * Realiza la comprobacion remota de la licencia contra el servidor.
 *
 * @return string  Estado devuelto por el servidor o codigo de error
 */
function dolisalesreport_check_remote_license()
{
	global $conf, $db;

	$license_key = getDolGlobalString('DOLISALESREPORT_LICENSE_KEY');
	// Normalizamos la URL de instalacion
	$install_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . DOL_URL_ROOT;
	$api_url = 'https://cv.iflorido.es/licencias/checklicence.php';

	$file_hash = hash_file('sha256', __FILE__);

	$query_params = http_build_query(array(
		'key' => $license_key,
		'url' => $install_url,
		'hash' => $file_hash,
		'module_slug' => 'dolisalesreport',
		'version' => '1.0.0',
	));

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_url . '?' . $query_params);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_USERAGENT, 'DoliSalesReport-License-Checker');

	// Compatibilidad SSL para servidores con CA bundle antiguo
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

	$response_json = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_error = curl_error($ch);
	curl_close($ch);

	$status = 'connection_error';

	if ($response_json === false) {
		dol_syslog("DoliSalesReport Licence Error: " . $curl_error, LOG_ERR);
		$status = 'connection_error';
	} elseif ($http_code != 200) {
		$status = 'http_error_' . $http_code;
	} else {
		$res = json_decode($response_json);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$status = 'api_error';
		} else {
			$status = isset($res->status) ? $res->status : 'unknown_error';
		}
	}

	dolibarr_set_const($db, "DOLISALESREPORT_LICENSE_STATUS", $status, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, "DOLISALESREPORT_LICENSE_LAST_CHECK", time(), 'chaine', 0, '', $conf->entity);

	return $status;
}

/**
 * Pinta un banner de estado de licencia y devuelve si es valida.
 * Centraliza la UI para reutilizarla en todas las paginas.
 *
 * @param string $status  Estado actual
 * @return bool           true si es 'valid'
 */
function dolisalesreport_print_license_banner($status)
{
	$bg_color     = "#fce4e4";
	$border_color = "#a44";
	$text_color   = "#a44";
	$icon         = "fa-exclamation-triangle";
	$status_label = "ATENCION: Su licencia es INVALIDA (" . strtoupper($status) . ")";

	switch ($status) {
		case 'valid':
			$bg_color = "#e4fcf0"; $border_color = "#4a4"; $text_color = "#4a4";
			$icon = "fa-check-circle";
			$status_label = "Estado de la licencia: VALIDA / ACTIVA";
			break;
		case 'tampered':
			$bg_color = "#fcf9e4"; $border_color = "#a94"; $text_color = "#a94";
			$icon = "fa-exclamation-circle";
			$status_label = "ADVERTENCIA: Archivos modificados (Hash de seguridad no coincide)";
			break;
		case 'expired':
			$bg_color = "#fcf9e4"; $border_color = "#a94"; $text_color = "#a94";
			$icon = "fa-clock";
			$status_label = "ADVERTENCIA: Su licencia ha caducado";
			break;
		case 'url_mismatch':
			$status_label = "ERROR: La URL de instalacion no coincide con la licencia registrada";
			break;
		case 'invalid_key':
			$status_label = "ERROR: La clave de licencia no existe en el sistema";
			break;
		case 'connection_error':
			$status_label = "ERROR: No se pudo conectar con el servidor de licencias remoto";
			break;
	}

	print '<div style="padding: 16px; font-weight: bold; border: 2px solid '.$border_color.'; background-color: '.$bg_color.'; color: '.$text_color.'; margin-bottom: 20px; border-radius: 8px;">';
	print '<span class="fa '.$icon.'" style="font-size: 1.4em; margin-right: 12px; vertical-align: middle;"></span>';
	print $status_label;
	if ($status != 'valid') {
		print '<div style="font-weight:normal; margin-top:8px; font-size:0.9em;">Configure una licencia valida en la pestana Licencia para habilitar la generacion y exportacion de informes.</div>';
	}
	print '</div>';

	return ($status == 'valid');
}
