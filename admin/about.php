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
require_once __DIR__.'/../core/modules/modLmdbSupplierOrderLimit.class.php';
$descriptor = new modLmdbSupplierOrderLimit($db);

$langs->loadLangs(array('admin', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

if (!isModEnabled('lmdbsupplierorderlimit') || !empty($user->socid)) {
	accessforbidden();
}

if (!$user->admin) {
	accessforbidden();
}

llxHeader('', $langs->trans('About'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('About'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Module').'</td><td>'.$langs->trans('LmdbSupplierOrderLimit').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($descriptor->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitAboutPublisher').'</td><td>'.dol_escape_htmltag($descriptor->editor_name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('LmdbSupplierOrderLimitDescription').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Compatibility').'</td><td>Dolibarr '.dol_escape_htmltag(implode('.', $descriptor->need_dolibarr_version)).'+ / PHP '.dol_escape_htmltag(implode('.', $descriptor->phpmin)).'+</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitAboutDependencies').'</td><td>'.$langs->trans('Module105Name').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitAboutMainFeatures').'</td><td>';
print '<ul>';
print '<li>'.$langs->trans('LmdbSupplierOrderLimitUser').'</li>';
print '<li>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</li>';
print '<li>Hook ordersuppliercard</li>';
print '<li>Trigger ORDER_SUPPLIER_APPROVE</li>';
print '</ul>';
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('LmdbSupplierOrderLimitAboutLicense').'</td><td>'.$langs->trans('LmdbSupplierOrderLimitPrivateModule').'</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
