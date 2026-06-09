<?php
/* Copyright (C) 2025 Ignacio Florido <https://cv.iflorido.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    dolisalesreport/core/modules/modDoliSalesReport.class.php
 * \ingroup dolisalesreport
 * \brief   Description and activation class for module DoliSalesReport
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliSalesReport
 */
class modDoliSalesReport extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		$this->numero = 500007;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'dolisalesreport';

		$this->family = "reporting";
		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Informes profesionales de ventas a partir de la facturacion";
		$this->descriptionlong = "Genera informes de ventas de productos y por cliente a partir de las facturas emitidas (incluidos tickets de TPV), con filtros de fecha, exportacion a Excel, dashboard y graficas.";

		$this->editor_name = 'Ignacio Florido';
		$this->editor_url = 'https://cv.iflorido.es';
		$this->version = '1.0.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Icono (clase .dolisalesreport para CSS personalizado)
		$this->picto = 'dolisalesreport@dolisalesreport';

		// --- module_parts ---
		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(
				'/dolisalesreport/css/dolisalesreport.css.php',
			),
			'js' => array(),
			'hooks' => array(),
		);

		$this->dirs = array("/dolisalesreport/temp");
		$this->config_page_url = array("setup.php@dolisalesreport");
		$this->langfiles = array("dolisalesreport@dolisalesreport");

		// COMPATIBILIDAD: PHP 7.4+ y Dolibarr 16 -> 22+
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(16, 0);

		// No requiere tablas propias: lee de la facturacion existente
		$this->const = array();

		// PERMISOS
		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero . '01';
		$this->rights[$r][1] = 'Ver informes de ventas';
		$this->rights[$r][4] = 'report';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . '02';
		$this->rights[$r][1] = 'Exportar informes de ventas a Excel';
		$this->rights[$r][4] = 'report';
		$this->rights[$r][5] = 'export';

		// MENUS
		$this->menu = array();
		$m = 0;

		// 1. Menu superior
		$this->menu[$m] = array(
			'fk_menu' => '0',
			'type' => 'top',
			'titre' => 'Informes Ventas',
			'mainmenu' => 'dolisalesreport',
			'url' => '/dolisalesreport/index.php',
			'langs' => 'dolisalesreport@dolisalesreport',
			'position' => 1000,
			'enabled' => '1',
			'perms' => '$user->rights->dolisalesreport->report->read',
			'user' => 2,
		);
		$m++;

		// 2. Submenu: Dashboard
		$this->menu[$m] = array(
			'fk_menu' => 'fk_mainmenu=dolisalesreport',
			'type' => 'left',
			'titre' => 'Dashboard',
			'mainmenu' => 'dolisalesreport',
			'leftmenu' => 'dashboard',
			'url' => '/dolisalesreport/index.php',
			'langs' => 'dolisalesreport@dolisalesreport',
			'position' => 1100,
			'enabled' => '1',
			'perms' => '$user->rights->dolisalesreport->report->read',
		);
		$m++;

		// 3. Submenu: Ventas por producto
		$this->menu[$m] = array(
			'fk_menu' => 'fk_mainmenu=dolisalesreport',
			'type' => 'left',
			'titre' => 'Ventas por producto',
			'mainmenu' => 'dolisalesreport',
			'leftmenu' => 'report_products',
			'url' => '/dolisalesreport/report_products.php',
			'langs' => 'dolisalesreport@dolisalesreport',
			'position' => 1200,
			'enabled' => '1',
			'perms' => '$user->rights->dolisalesreport->report->read',
		);
		$m++;

		// 4. Submenu: Configuracion
		$this->menu[$m] = array(
			'fk_menu' => 'fk_mainmenu=dolisalesreport',
			'type' => 'left',
			'titre' => 'Configuracion',
			'mainmenu' => 'dolisalesreport',
			'leftmenu' => 'setup',
			'url' => '/dolisalesreport/admin/setup.php',
			'langs' => 'dolisalesreport@dolisalesreport',
			'position' => 1300,
			'enabled' => '1',
			'perms' => '$user->admin',
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options
	 * @return int            1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options
	 * @return int            1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
