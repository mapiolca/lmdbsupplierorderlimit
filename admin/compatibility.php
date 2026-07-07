<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');

$langs->loadLangs(array('admin', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

if (!isModEnabled('lmdbsupplierorderlimit')) {
	accessforbidden();
}

if (!lmdbsupplierorderlimitUserCan($user, 'config', 'write')) {
	accessforbidden();
}

llxHeader('', $langs->trans('Compatibility'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('Compatibility'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitDetectedDolibarr').'</td><td>'.dol_escape_htmltag((string) DOL_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitDetectedPhp').'</td><td>'.dol_escape_htmltag((string) PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitMinDolibarr').'</td><td>20.0.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitMinPhp').'</td><td>8.0.0</td></tr>';
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Feature').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td>'.$langs->trans('LmdbSupplierOrderLimitMinDolibarr').'</td>';
print '<td>'.$langs->trans('LmdbSupplierOrderLimitMinPhp').'</td>';
print '<td>'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('Reason').'</td>';
print '</tr>';

$features = lmdbsupplierorderlimitGetCompatibilityFeatures();
foreach ($features as $feature) {
	$available = !empty($feature['available']);
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans((string) $feature['label']).'</td>';
	print '<td>'.$langs->trans((string) $feature['description']).'</td>';
	print '<td>'.dol_escape_htmltag((string) $feature['module_available_from']).'</td>';
	print '<td>'.dol_escape_htmltag((string) $feature['min_php']).'</td>';
	print '<td><span class="badge '.($available ? 'badge-status4' : 'badge-status8').'">'.($available ? $langs->trans('LmdbSupplierOrderLimitFeatureAvailable') : $langs->trans('LmdbSupplierOrderLimitFeatureUnavailable')).'</span></td>';
	print '<td>'.$langs->trans((string) $feature['reason']).'</td>';
	print '</tr>';
}

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
