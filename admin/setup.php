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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
require_once __DIR__.'/../class/lmdbsupplierorderlimitconsumption.class.php';

$langs->loadLangs(array('admin', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

$action = GETPOST('action', 'aZ09');

if (!isModEnabled('lmdbsupplierorderlimit') || !empty($user->socid)) {
	accessforbidden();
}

if (!$user->admin) {
	accessforbidden();
}

if ($action === 'save') {
	$defaultNoLimitBehavior = GETPOST('default_no_limit_behavior', 'alpha');
	if (!in_array($defaultNoLimitBehavior, array('deny', 'unlimited'), true)) {
		accessforbidden();
	}

	$periodModes = array();
	foreach (array('DAY','MONTH','YEAR') as $period) {
		$mode = GETPOST('period_'.$period, 'alpha');
		if (!in_array($mode, array('civil','rolling'), true)) { accessforbidden(); }
		$periodModes[$period] = $mode;
	}
	$db->begin();
	$result = dolibarr_set_const($db, 'LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR', $defaultNoLimitBehavior, 'chaine', 0, '', (int) $conf->entity);
	foreach ($periodModes as $period => $mode) {
		if (dolibarr_set_const($db, 'LMDBSUPPLIERORDERLIMIT_'.$period.'_MODE', $mode, 'chaine', 0, '', (int) $conf->entity) <= 0) { $result = -1; }
	}
	if ($result > 0) {
		$db->commit();
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	$db->rollback();
	setEventMessages($langs->trans('LimitTechnicalError'), null, 'errors');
}

if ($action === 'reconcile') {
	$db->begin();
	try {
		$ledger = new LmdbSupplierOrderLimitConsumption($db);
		$ambiguous = $ledger->reconcile((int) $conf->entity);
		$db->commit();
		setEventMessages($langs->trans($ambiguous ? 'LimitHistoryIncomplete' : 'RecordSaved'), null, $ambiguous ? 'warnings' : 'mesgs');
	} catch (Throwable $e) {
		$db->rollback();
		setEventMessages($langs->trans('LimitTechnicalError'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
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
$defaultNoLimitBehavior = getDolGlobalString('LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR', 'unlimited');
if (!in_array($defaultNoLimitBehavior, array('deny', 'unlimited'), true)) {
	$defaultNoLimitBehavior = 'unlimited';
}
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="action" value="save">';
print '<select class="flat minwidth200" name="default_no_limit_behavior" id="default_no_limit_behavior">';
print '<option value="unlimited"'.($defaultNoLimitBehavior === 'unlimited' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitDefaultUnlimited').'</option>';
print '<option value="deny"'.($defaultNoLimitBehavior === 'deny' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitDefaultDeny').'</option>';
print '</select> ';
foreach (array('DAY' => 'LimitTypeDay','MONTH' => 'LimitTypeMonth','YEAR' => 'LimitTypeYear') as $period => $label) {
	print '<p>'.$langs->trans($label).' ';
	$mode = getDolGlobalString('LMDBSUPPLIERORDERLIMIT_'.$period.'_MODE', 'civil');
	print Form::selectarray('period_'.$period, array('civil' => $langs->trans('LimitPeriodCivil'), 'rolling' => $langs->trans('LimitPeriodRolling')), $mode);
	print ajax_combobox('period_'.$period).'</p>';
}
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</form>';
print ajax_combobox('default_no_limit_behavior');
print '</td>';
print '</tr>';

print '</table>';
print '<p>'.$langs->trans('LimitPriorityHelp').'</p>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.$token.'"><input type="hidden" name="action" value="reconcile">';
print '<p>'.$langs->trans('LimitHistoryHelp').'</p><button type="submit" class="button">'.$langs->trans('LimitReconcile').'</button></form>';

// Only show ambiguous orders the administrator is also allowed to read as business data.
if ($user->hasRight('fournisseur', 'commande', 'lire')) {
	$sql = 'SELECT c.rowid,c.ref,c.entity,c.fk_statut FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption r';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur c ON c.rowid = r.fk_supplier_order AND c.entity = r.entity';
	$sql .= ' WHERE r.entity = '.(int) $conf->entity.' AND r.active = 1 AND r.unresolved = 1';
	if (!$user->hasRight('societe', 'client', 'voir')) {
		$sql .= ' AND EXISTS (SELECT sc.fk_soc FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc = c.fk_soc AND sc.fk_user = '.(int) $user->id.')';
	}
	$sql .= ' ORDER BY c.rowid'.$db->plimit(50);
	$result = $db->query($sql);
	if (!$result) { setEventMessages($langs->trans('LimitTechnicalError'), null, 'errors'); }
	else {
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('LimitHistoryIncomplete').'</td></tr>';
		$count = 0;
		while (is_object($row = $db->fetch_object($result))) {
			$order = new CommandeFournisseur($db);
			$order->id = (int) $row->rowid;
			$order->entity = (int) $row->entity;
			$order->ref = $row->ref;
			$order->status = (int) $row->fk_statut;
			$order->statut = (int) $row->fk_statut;
			print '<tr class="oddeven"><td>'.$order->getNomUrl(1).'</td></tr>';
			$count++;
		}
		if (!$count) { print '<tr class="oddeven"><td><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>'; }
		print '</table></div>';
	}
}

print dol_get_fiche_end();

llxFooter();
$db->close();
