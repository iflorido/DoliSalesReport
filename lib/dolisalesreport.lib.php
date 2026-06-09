<?php
/**
 * \file    dolisalesreport/lib/dolisalesreport.lib.php
 * \ingroup dolisalesreport
 * \brief   Library files with common functions for DoliSalesReport
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function dolisalesreportAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("dolisalesreport@dolisalesreport");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/dolisalesreport/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisalesreport/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisalesreport/admin/licence.php", 1);
	$head[$h][1] = $langs->trans("Licence");
	$head[$h][2] = 'licence';
	$h++;

	return $head;
}
