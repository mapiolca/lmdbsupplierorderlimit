<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');

$langs->loadLangs(array('admin', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

$action = GETPOST('action', 'aZ09');

if (!isModEnabled('lmdbsupplierorderlimit')) {
	accessforbidden();
}

if (!lmdbsupplierorderlimitUserCan($user, 'config', 'write')) {
	accessforbidden();
}

if ($action === 'save') {
	$defaultNoLimitBehavior = GETPOST('default_no_limit_behavior', 'alpha');
	if ($defaultNoLimitBehavior !== 'deny') {
		$defaultNoLimitBehavior = 'deny';
	}

	$result = dolibarr_set_const($db, 'LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR', $defaultNoLimitBehavior, 'chaine', 0, '', (int) $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	setEventMessages($db->lasterror(), null, 'errors');
}

$token = newToken();

llxHeader('', $langs->trans('LmdbSupplierOrderLimitSetup'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('LmdbSupplierOrderLimitSetup'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

$switchConstants = array(
	'LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE' => 'LmdbSupplierOrderLimitShowDeniedMessage',
	'LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS' => 'LmdbSupplierOrderLimitLogAllowedApprovals',
	'LMDBSUPPLIERORDERLIMIT_LOG_DENIED_APPROVALS' => 'LmdbSupplierOrderLimitLogDeniedApprovals',
	'LMDBSUPPLIERORDERLIMIT_DIRECT_USER_PRIORITY' => 'LmdbSupplierOrderLimitDirectUserPriority',
);

foreach ($switchConstants as $constant => $labelKey) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($labelKey).'</td>';
	print '<td>';
	if (function_exists('ajax_constantonoff')) {
		print ajax_constantonoff($constant);
	} else {
		print getDolGlobalInt($constant, $constant === 'LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS' ? 0 : 1) ? $langs->trans('Yes') : $langs->trans('No');
	}
	print '</td>';
	print '</tr>';
}

print '<tr class="oddeven">';
print '<td>'.$langs->trans('LmdbSupplierOrderLimitDefaultNoLimitBehavior').'</td>';
print '<td>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="action" value="save">';
print '<select class="flat minwidth200" name="default_no_limit_behavior" id="default_no_limit_behavior">';
print '<option value="deny" selected>'.$langs->trans('LmdbSupplierOrderLimitDefaultDeny').'</option>';
print '</select> ';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</form>';
print ajax_combobox('default_no_limit_behavior');
print '</td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
