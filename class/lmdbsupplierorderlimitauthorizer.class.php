<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');

/**
 * Central supplier order approval authorizer.
 *
 * @phpstan-type ApprovalDecision array{
 *     allowed: bool,
 *     reason: string,
 *     order_amount_ht: string|null,
 *     limit_amount_ht: string|null,
 *     limit_unlimited: int,
 *     limit_source: string|null,
 *     fk_limit: int|null,
 *     approval_level: int
 * }
 * @phpstan-type ApplicableLimit array{
 *     fk_limit: int|null,
 *     amount_ht: string|null,
 *     unlimited: int,
 *     source: string|null
 * }
 */
class LmdbSupplierOrderLimitAuthorizer
{
	/**
	 * Decide if a user can approve a supplier order.
	 *
	 * @param DoliDB $db            Database handler
	 * @param User   $user          User
	 * @param mixed  $order         Supplier order
	 * @param int    $approvalLevel Approval level, 1 or 2
	 * @return array<string, mixed>
	 */
	public static function canApproveSupplierOrder($db, $user, $order, $approvalLevel = 1)
	{
		global $conf;

		$approvalLevel = ((int) $approvalLevel === 2) ? 2 : 1;

		if (!lmdbsupplierorderlimitIsSupplierOrderLike($order)) {
			return self::buildDecision(false, 'object_not_supplier_order', null, null, 0, null, null, $approvalLevel);
		}

		$orderAmount = self::extractOrderAmount($order);
		if ($orderAmount === null) {
			return self::buildDecision(false, 'invalid_amount', null, null, 0, null, null, $approvalLevel);
		}

		if (lmdbsupplierorderlimitUserIsAdministrator($user)) {
			return self::buildDecision(true, 'admin_unlimited', $orderAmount, null, 1, 'admin', null, $approvalLevel);
		}

		$nativeRight = $approvalLevel === 2 ? 'approve2' : 'approuver';
		if (!is_object($user) || !$user->hasRight('fournisseur', 'commande', $nativeRight)) {
			return self::buildDecision(false, 'native_permission_missing', $orderAmount, null, 0, null, null, $approvalLevel);
		}

		$entity = isset($order->entity) && (int) $order->entity > 0 ? (int) $order->entity : (int) $conf->entity;
		$limit = self::resolveApplicableLimit($db, $user, $entity);
		if (empty($limit['fk_limit']) && empty($limit['unlimited']) && $limit['source'] === null) {
			return self::buildDecision(false, 'no_limit_found', $orderAmount, null, 0, null, null, $approvalLevel);
		}

		if (!empty($limit['unlimited'])) {
			return self::buildDecision(true, 'unlimited', $orderAmount, null, 1, (string) $limit['source'], (int) $limit['fk_limit'], $approvalLevel);
		}

		if ($limit['amount_ht'] === null || self::normalizeAmount($limit['amount_ht']) === null) {
			return self::buildDecision(false, 'invalid_amount', $orderAmount, null, 0, (string) $limit['source'], (int) $limit['fk_limit'], $approvalLevel);
		}

		$allowed = self::compareDecimalAmount($orderAmount, (string) $limit['amount_ht']) <= 0;

		return self::buildDecision(
			$allowed,
			$allowed ? 'allowed' : 'amount_over_limit',
			$orderAmount,
			(string) $limit['amount_ht'],
			0,
			(string) $limit['source'],
			(int) $limit['fk_limit'],
			$approvalLevel
		);
	}

	/**
	 * Resolve applicable user or group limit.
	 *
	 * @param DoliDB $db     Database handler
	 * @param User   $user   User
	 * @param int    $entity Entity id
	 * @return array<string, mixed>
	 */
	public static function resolveApplicableLimit($db, $user, $entity)
	{
		$directUserPriority = getDolGlobalInt('LMDBSUPPLIERORDERLIMIT_DIRECT_USER_PRIORITY', 1);

		if ($directUserPriority) {
			$userLimit = self::fetchUserLimit($db, $user, $entity);
			if (!empty($userLimit['fk_limit'])) {
				return $userLimit;
			}

			return self::fetchGroupLimits($db, $user, $entity);
		}

		$userLimit = self::fetchUserLimit($db, $user, $entity);
		$groupLimit = self::fetchGroupLimits($db, $user, $entity);

		if (!empty($userLimit['unlimited'])) {
			return $userLimit;
		}
		if (!empty($groupLimit['unlimited'])) {
			return $groupLimit;
		}
		if (!empty($userLimit['fk_limit']) && empty($groupLimit['fk_limit'])) {
			return $userLimit;
		}
		if (empty($userLimit['fk_limit']) && !empty($groupLimit['fk_limit'])) {
			return $groupLimit;
		}
		if (!empty($userLimit['fk_limit']) && !empty($groupLimit['fk_limit'])) {
			return self::compareDecimalAmount((string) $userLimit['amount_ht'], (string) $groupLimit['amount_ht']) >= 0 ? $userLimit : $groupLimit;
		}

		return self::emptyLimit();
	}

	/**
	 * Fetch direct user limit.
	 *
	 * @param DoliDB $db     Database handler
	 * @param User   $user   User
	 * @param int    $entity Entity id
	 * @return array<string, mixed>
	 */
	public static function fetchUserLimit($db, $user, $entity)
	{
		$sql = 'SELECT t.rowid, t.amount_ht, t.unlimited';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_limit AS t';
		$sql .= ' WHERE t.entity = '.((int) $entity);
		$sql .= ' AND t.active = 1';
		$sql .= ' AND t.fk_user = '.((int) $user->id);
		$sql .= ' AND t.fk_usergroup IS NULL';
		$sql .= self::sqlCurrentPeriod($db);
		$sql .= ' ORDER BY t.unlimited DESC, t.amount_ht DESC, t.rowid DESC';
		$sql .= $db->plimit(1);

		$resql = $db->query($sql);
		if (!$resql) {
			return self::emptyLimit();
		}

		$obj = $db->fetch_object($resql);
		if (!is_object($obj)) {
			return self::emptyLimit();
		}

		return array(
			'fk_limit' => (int) $obj->rowid,
			'amount_ht' => $obj->amount_ht !== null ? self::normalizeAmount((string) $obj->amount_ht) : null,
			'unlimited' => (int) $obj->unlimited,
			'source' => 'user',
		);
	}

	/**
	 * Fetch highest active group limit.
	 *
	 * @param DoliDB $db     Database handler
	 * @param User   $user   User
	 * @param int    $entity Entity id
	 * @return array<string, mixed>
	 */
	public static function fetchGroupLimits($db, $user, $entity)
	{
		$sql = 'SELECT t.rowid, t.amount_ht, t.unlimited';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_limit AS t';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup_user AS ugu ON ugu.fk_usergroup = t.fk_usergroup';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'usergroup AS ug ON ug.rowid = t.fk_usergroup';
		$sql .= ' WHERE t.entity = '.((int) $entity);
		$sql .= ' AND t.active = 1';
		$sql .= ' AND t.fk_user IS NULL';
		$sql .= ' AND ugu.fk_user = '.((int) $user->id);
		$sql .= ' AND (ug.entity IS NULL OR ug.entity = '.((int) $entity).')';
		$sql .= self::sqlCurrentPeriod($db);
		$sql .= ' ORDER BY t.unlimited DESC, t.amount_ht DESC, t.rowid DESC';
		$sql .= $db->plimit(1);

		$resql = $db->query($sql);
		if (!$resql) {
			return self::emptyLimit();
		}

		$obj = $db->fetch_object($resql);
		if (!is_object($obj)) {
			return self::emptyLimit();
		}

		return array(
			'fk_limit' => (int) $obj->rowid,
			'amount_ht' => $obj->amount_ht !== null ? self::normalizeAmount((string) $obj->amount_ht) : null,
			'unlimited' => (int) $obj->unlimited,
			'source' => 'group',
		);
	}

	/**
	 * Compare two positive decimal amounts without float rounding.
	 *
	 * @param string|int|float|null $left  Left amount
	 * @param string|int|float|null $right Right amount
	 * @return int -1, 0 or 1
	 */
	public static function compareDecimalAmount($left, $right)
	{
		$leftNormalized = self::normalizeAmount($left);
		$rightNormalized = self::normalizeAmount($right);

		if ($leftNormalized === null || $rightNormalized === null) {
			return 1;
		}

		$leftParts = explode('.', $leftNormalized);
		$rightParts = explode('.', $rightNormalized);

		$leftInteger = ltrim($leftParts[0], '0');
		$rightInteger = ltrim($rightParts[0], '0');
		$leftInteger = $leftInteger === '' ? '0' : $leftInteger;
		$rightInteger = $rightInteger === '' ? '0' : $rightInteger;

		if (strlen($leftInteger) !== strlen($rightInteger)) {
			return strlen($leftInteger) < strlen($rightInteger) ? -1 : 1;
		}

		$integerCompare = strcmp($leftInteger, $rightInteger);
		if ($integerCompare !== 0) {
			return $integerCompare < 0 ? -1 : 1;
		}

		$leftDecimal = isset($leftParts[1]) ? $leftParts[1] : '00000000';
		$rightDecimal = isset($rightParts[1]) ? $rightParts[1] : '00000000';
		$decimalCompare = strcmp($leftDecimal, $rightDecimal);

		if ($decimalCompare === 0) {
			return 0;
		}

		return $decimalCompare < 0 ? -1 : 1;
	}

	/**
	 * Normalize an amount to a positive decimal string with 8 decimals.
	 *
	 * @param string|int|float|null $amount Amount
	 * @return string|null
	 */
	public static function normalizeAmount($amount)
	{
		if ($amount === null || $amount === '') {
			return null;
		}

		$value = function_exists('price2num') ? (string) price2num($amount, 'MU') : (string) $amount;
		$value = trim(str_replace(' ', '', $value));
		$value = str_replace(',', '.', $value);

		if (!preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/', $value)) {
			return null;
		}

		$parts = explode('.', $value, 2);
		$integer = ltrim($parts[0], '0');
		$integer = $integer === '' ? '0' : $integer;
		$decimal = isset($parts[1]) ? $parts[1] : '';
		$decimal = substr(str_pad($decimal, 8, '0'), 0, 8);

		return $integer.'.'.$decimal;
	}

	/**
	 * Build decision array.
	 *
	 * @param bool        $allowed        Is allowed
	 * @param string      $reason         Reason code
	 * @param string|null $orderAmountHt  Order amount
	 * @param string|null $limitAmountHt  Limit amount
	 * @param int         $limitUnlimited Unlimited flag
	 * @param string|null $limitSource    Limit source
	 * @param int|null    $fkLimit        Limit id
	 * @param int         $approvalLevel  Approval level
	 * @return array<string, mixed>
	 */
	public static function buildDecision($allowed, $reason, $orderAmountHt, $limitAmountHt, $limitUnlimited, $limitSource, $fkLimit, $approvalLevel)
	{
		return array(
			'allowed' => (bool) $allowed,
			'reason' => $reason,
			'order_amount_ht' => $orderAmountHt,
			'limit_amount_ht' => $limitAmountHt,
			'limit_unlimited' => (int) $limitUnlimited,
			'limit_source' => $limitSource,
			'fk_limit' => $fkLimit,
			'approval_level' => (int) $approvalLevel,
		);
	}

	/**
	 * Format a human-readable decision message.
	 *
	 * @param array<string, mixed> $decision Decision array
	 * @return string
	 */
	public static function formatDecisionMessage($decision)
	{
		global $langs;

		$reason = isset($decision['reason']) ? (string) $decision['reason'] : 'technical_error';

		if ($reason === 'amount_over_limit') {
			$orderAmount = isset($decision['order_amount_ht']) && $decision['order_amount_ht'] !== null ? self::formatTotalAmountForDisplay((string) $decision['order_amount_ht']) : '';
			$limitAmount = isset($decision['limit_amount_ht']) && $decision['limit_amount_ht'] !== null ? self::formatTotalAmountForDisplay((string) $decision['limit_amount_ht']) : '';
			return $langs->trans('LmdbSupplierOrderLimitAmountOverLimit').' '.$langs->trans('LmdbSupplierOrderLimitOrderAmount').': '.$orderAmount.' / '.$langs->trans('LmdbSupplierOrderLimitApplicableLimit').': '.$limitAmount;
		}

		if ($reason === 'no_limit_found') {
			return $langs->trans('LmdbSupplierOrderLimitNoLimitFound');
		}

		if ($reason === 'native_permission_missing') {
			return $langs->trans('LmdbSupplierOrderLimitNativePermissionMissing');
		}

		if ($reason === 'object_not_supplier_order') {
			return $langs->trans('LmdbSupplierOrderLimitObjectNotSupplierOrder');
		}

		if ($reason === 'invalid_amount') {
			return $langs->trans('LmdbSupplierOrderLimitInvalidAmount');
		}

		return $langs->trans('LmdbSupplierOrderLimitApprovalDenied');
	}

	/**
	 * Format an amount with Dolibarr total rounding settings.
	 *
	 * @param string $amount Normalized amount
	 * @return string
	 */
	public static function formatTotalAmountForDisplay($amount)
	{
		$rounded = function_exists('price2num') ? price2num($amount, 'MT') : $amount;

		if (function_exists('price')) {
			return price((float) $rounded);
		}

		$decimals = function_exists('getDolGlobalInt') ? getDolGlobalInt('MAIN_MAX_DECIMALS_TOT', 2) : 2;
		return number_format((float) $rounded, $decimals, '.', ' ');
	}

	/**
	 * Extract normalized supplier order amount.
	 *
	 * @param mixed $order Supplier order
	 * @return string|null
	 */
	private static function extractOrderAmount($order)
	{
		if (!is_object($order) || !isset($order->total_ht)) {
			return null;
		}

		return self::normalizeAmount($order->total_ht);
	}

	/**
	 * Return current period SQL condition.
	 *
	 * @param DoliDB $db Database handler
	 * @return string
	 */
	private static function sqlCurrentPeriod($db)
	{
		$now = $db->idate(dol_now());
		return " AND (t.date_start IS NULL OR t.date_start <= '".$db->escape($now)."') AND (t.date_end IS NULL OR t.date_end >= '".$db->escape($now)."')";
	}

	/**
	 * Return empty limit result.
	 *
	 * @return array<string, mixed>
	 */
	private static function emptyLimit()
	{
		return array(
			'fk_limit' => null,
			'amount_ht' => null,
			'unlimited' => 0,
			'source' => null,
		);
	}
}
