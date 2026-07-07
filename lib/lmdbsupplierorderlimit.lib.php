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
	global $langs;

	$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/lmdbsupplierorderlimit/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbsupplierorderlimit/admin/limits.php', 1);
	$head[$h][1] = $langs->trans('LmdbSupplierOrderLimitLimits');
	$head[$h][2] = 'limits';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbsupplierorderlimit/admin/logs.php', 1);
	$head[$h][1] = $langs->trans('LmdbSupplierOrderLimitLogs');
	$head[$h][2] = 'logs';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbsupplierorderlimit/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbsupplierorderlimit/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';

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
 * Submit a native Dolibarr list form when the page-size selector changes.
 *
 * @param string $formId HTML form id
 * @return void
 */
function lmdbsupplierorderlimitPrintListLimitAutoSubmitScript($formId)
{
	print '<script>';
	print 'jQuery(function($) {';
	print 'var $form = $("#'.dol_escape_js($formId).'");';
	print 'if (!$form.length) { return; }';
	print '$form.find("select.selectlimit, select[name=limit]").off("change.lmdbsupplierorderlimitLimit").on("change.lmdbsupplierorderlimitLimit", function() {';
	print 'var $page = $form.find("input[name=page]");';
	print 'if ($page.length) { $page.val("0"); }';
	print '$form.trigger("submit");';
	print '});';
	print '});';
	print '</script>';
}

/**
 * Centralized compatibility feature list.
 *
 * @return array<string, array<string, mixed>>
 */
function lmdbsupplierorderlimitGetCompatibilityFeatures()
{
	return array(
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
 * Business permission helper with Dolibarr admin elevation.
 *
 * @param User   $user   User object
 * @param string $object Permission object
 * @param string $action Permission action
 * @return bool
 */
function lmdbsupplierorderlimitUserCan($user, $object, $action)
{
	if (!is_object($user)) {
		return false;
	}

	if (lmdbsupplierorderlimitUserIsAdministrator($user)) {
		return true;
	}

	return $user->hasRight('lmdbsupplierorderlimit', $object, $action);
}

/**
 * Check if user must receive full module functional rights.
 *
 * Covers Dolibarr administrators, global/super administrators and common
 * Multicompany administrator rights without requiring module granular rights.
 *
 * @param User $user User object
 * @return bool
 */
function lmdbsupplierorderlimitUserIsAdministrator($user)
{
	if (!is_object($user)) {
		return false;
	}

	if (!empty($user->admin) || !empty($user->superadmin)) {
		return true;
	}

	if (isset($user->entity) && (int) $user->entity === 0 && !empty($user->admin)) {
		return true;
	}

	if (method_exists($user, 'hasRight')) {
		if ($user->hasRight('multicompany', 'admin', 'write') || $user->hasRight('multicompany', 'admin', 'read')) {
			return true;
		}
	}

	return false;
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
