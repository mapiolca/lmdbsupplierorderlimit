<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare module admin tabs.
 *
 * @return array<int, array{0:string, 1:string, 2:string}>
 */
function lmdbsupplierorderlimitAdminPrepareHead()
{
	global $langs, $user;
	$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');
	$head = array();
	if ($user->admin) {
		$head[] = array(dol_buildpath('/lmdbsupplierorderlimit/admin/setup.php', 1), $langs->trans('Settings'), 'settings');
	}
	if ($user->hasRight('lmdbsupplierorderlimit', 'limit', 'read')) {
		$head[] = array(dol_buildpath('/lmdbsupplierorderlimit/admin/limits.php', 1), $langs->trans('LmdbSupplierOrderLimitLimits'), 'limits');
	}
	if ($user->hasRight('lmdbsupplierorderlimit', 'log', 'read')) {
		$head[] = array(dol_buildpath('/lmdbsupplierorderlimit/admin/logs.php', 1), $langs->trans('LmdbSupplierOrderLimitLogs'), 'logs');
	}
	if ($user->admin) {
		$head[] = array(dol_buildpath('/lmdbsupplierorderlimit/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility');
		$head[] = array(dol_buildpath('/lmdbsupplierorderlimit/admin/about.php', 1), $langs->trans('About'), 'about');
	}

	return $head;
}

/**
 * Return link back to native modules list.
 *
 * @return string
 */
function lmdbsupplierorderlimitBackToModuleListLink()
{
	global $langs;

	return '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('lmdbsupplierorderlimit').'">'.$langs->trans('BackToModuleList').'</a>';
}

/**
 * Centralized compatibility feature list.
 *
 * @return array<string, array<string, mixed>>
 */
function lmdbsupplierorderlimitGetCompatibilityFeatures()
{
	global $db, $conf;
	require_once __DIR__.'/../class/lmdbsupplierorderlimitconsumption.class.php';
	$ready = true;
	$reason = 'LimitCompatibilityTests';
	try { (new LmdbSupplierOrderLimitConsumption($db))->assertReady((int) $conf->entity); }
	catch (Throwable $e) { $ready = false; $reason = $e->getMessage() === 'history_incomplete' ? 'LimitHistoryIncomplete' : 'LimitTechnicalError'; }
	return array(
		'periodic_consumption' => array(
			'label' => 'LimitReconcile', 'description' => 'LimitHistoryHelp',
			'min_dolibarr' => '20.0.0', 'module_available_from' => '20.0.0', 'min_php' => '8.0.0',
			'available' => $ready, 'reason' => $reason,
		),
		'financial_supplier_order_approval_limit' => array(
			'label' => 'LmdbSupplierOrderLimitCompatibilityFinancialLimit',
			'description' => 'LmdbSupplierOrderLimitCompatibilityFinancialLimitDesc',
			'min_dolibarr' => '20.0.0',
			'core_available_from' => '20.0.0',
			'module_available_from' => '20.0.0',
			'min_php' => '8.0.0',
			'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '>=')",
			'available' => version_compare(DOL_VERSION, '20.0.0', '>=') && version_compare(PHP_VERSION, '8.0.0', '>='),
			'reason' => 'LmdbSupplierOrderLimitRequiresDolibarr20Php80',
		),
		'supplier_order_card_hook' => array(
			'label' => 'LmdbSupplierOrderLimitCompatibilitySupplierOrderHook',
			'description' => 'LmdbSupplierOrderLimitCompatibilitySupplierOrderHookDesc',
			'min_dolibarr' => '20.0.0',
			'core_available_from' => '20.0.0',
			'module_available_from' => '20.0.0',
			'min_php' => '8.0.0',
			'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=')",
			'available' => version_compare(DOL_VERSION, '20.0.0', '>='),
			'reason' => 'LmdbSupplierOrderLimitRequiresSupplierOrderCardHook',
		),
		'supplier_order_approve_trigger' => array(
			'label' => 'LmdbSupplierOrderLimitCompatibilitySupplierOrderTrigger',
			'description' => 'LmdbSupplierOrderLimitCompatibilitySupplierOrderTriggerDesc',
			'min_dolibarr' => '20.0.0',
			'core_available_from' => '20.0.0',
			'module_available_from' => '20.0.0',
			'min_php' => '8.0.0',
			'compatibility_check' => "version_compare(DOL_VERSION, '20.0.0', '>=')",
			'available' => version_compare(DOL_VERSION, '20.0.0', '>='),
			'reason' => 'LmdbSupplierOrderLimitRequiresSupplierOrderApproveTrigger',
		),
	);
}

/**
 * Check if an object looks like a native supplier order.
 *
 * @param mixed $object Object to test
 * @return bool
 */
function lmdbsupplierorderlimitIsSupplierOrderLike($object)
{
	if (!is_object($object)) {
		return false;
	}

	if (class_exists('CommandeFournisseur') && $object instanceof CommandeFournisseur) {
		return true;
	}

	$element = isset($object->element) ? (string) $object->element : '';
	$tableelement = isset($object->table_element) ? (string) $object->table_element : '';

	return $element === 'order_supplier' || $tableelement === 'commande_fournisseur';
}
