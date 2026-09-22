<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/../lib/lmdbsupplierorderlimit.lib.php';
require_once __DIR__.'/lmdbsupplierorderlimitpolicy.class.php';
require_once __DIR__.'/lmdbsupplierorderlimitscope.class.php';
require_once __DIR__.'/lmdbsupplierorderlimitconsumption.class.php';

/**
 * Read-only decisions. The native operation owns the final transaction.
 * @phpstan-type SelectedRule array{rowid:int,entity:int,fk_user:int,fk_usergroup:int,limit_type:string,amount_ht:?string,unlimited:int,source:string}
 * @phpstan-type LimitCheck array{type:string,rule:int,entity:int,source:string,period:?array{start:int,end:int},ceiling:?string,consumed:?string,projected:?string,allowed:bool,reason:string}
 * @phpstan-type LimitDecision array{allowed:bool,reason:string,order_amount_ht:?string,limit_amount_ht:?string,limit_unlimited:int,limit_source:?string,fk_limit:?int,approval_level:int,checks:list<LimitCheck>}
 */
class LmdbSupplierOrderLimitAuthorizer
{
	/**
	 * @param DoliDB $db
	 * @param User $user Acting user
	 * @param CommandeFournisseur $order
	 * @param int $approvalLevel
	 * @param bool $locked Current reads after acquisition of module locks
	 * @param bool $final Never bypass financial controls on final approval
	 * @param int|null $approvalDate Original date for an amendment
	 * @param int|null $subjectId Original approver for an amendment
	 * @return LimitDecision
	 */
	public static function canApproveSupplierOrder($db, $user, $order, $approvalLevel = 1, bool $locked = false, bool $final = false, ?int $approvalDate = null, ?int $subjectId = null): array
	{
		$decision = array('allowed' => false, 'reason' => 'technical_error', 'order_amount_ht' => null, 'limit_amount_ht' => null, 'limit_unlimited' => 0, 'limit_source' => null, 'fk_limit' => null, 'approval_level' => $approvalLevel, 'checks' => array());
		try {
			if (!lmdbsupplierorderlimitIsSupplierOrderLike($order) || (int) $order->id <= 0 || (int) $order->entity <= 0) { throw new RuntimeException('object_not_supplier_order'); }
			$entity = (int) $order->entity;
			if (!in_array($entity, array_map('intval', explode(',', getEntity('supplier_order'))), true)
				|| !$user->hasRight('fournisseur', 'commande', 'lire')
				|| restrictedArea($user, 'fournisseur', $order, 'commande_fournisseur', 'commande', 'fk_soc', 'rowid', 0, 1) <= 0) { throw new RuntimeException('native_permission_missing'); }
			if ($subjectId === null && !$user->hasRight('fournisseur', 'commande', $approvalLevel === 2 ? 'approve2' : 'approuver')) { throw new RuntimeException('native_permission_missing'); }
			if ($subjectId !== null && !$user->hasRight('fournisseur', 'commande', 'creer')) { throw new RuntimeException('native_permission_missing'); }
			$amount = LmdbSupplierOrderLimitPolicy::amount($order->total_ht);
			if ($amount === null) { throw new RuntimeException('invalid_amount'); }
			$decision['order_amount_ht'] = $amount;
			if (!$final && $approvalLevel === 1 && $user->hasRight('fournisseur', 'commande', 'approve2')) {
				return array_replace($decision, array('allowed' => true, 'reason' => 'first_level_allowed_by_second_level_permission'));
			}
			$scope = LmdbSupplierOrderLimitScope::context($db, $entity);
			$subjectId = $subjectId ?? (int) $user->id;
			$now = dol_now();
			$rules = self::rules($db, $scope, $subjectId, $entity, $now, $locked);
			$financialEntities = array($entity);
			foreach ($rules as $rule) { if (!$rule['unlimited'] && $rule['limit_type'] !== 'project_budget') { $financialEntities[] = $rule['entity']; } }
			LmdbSupplierOrderLimitScope::assertCommonCurrency($db, $financialEntities);
			if (!$rules) {
				return array_replace($decision, array('allowed' => $scope['settings']['LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR'] === 'unlimited', 'reason' => 'no_limit_found'));
			}
			$ledger = new LmdbSupplierOrderLimitConsumption($db);
			$decision['allowed'] = true;
			$decision['reason'] = 'allowed';
			foreach ($rules as $type => $rule) {
				$check = array('type' => $type, 'rule' => $rule['rowid'], 'entity' => $rule['entity'], 'source' => $rule['source'], 'period' => null, 'ceiling' => $rule['amount_ht'], 'consumed' => '0', 'projected' => $amount, 'allowed' => true, 'reason' => 'allowed');
				if ($rule['unlimited']) {
					$check['ceiling'] = null;
					$check['reason'] = 'unlimited';
				} elseif ($type === 'project_budget') {
					$projectId = (int) ($order->fk_project ?? 0);
					if (!$projectId) {
						$check['reason'] = 'no_project';
					} else {
						require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
						$project = new Project($db);
						if ((!$user->hasRight('projet', 'lire') && !$user->hasRight('projet', 'all', 'lire')) || $project->fetch($projectId) <= 0
							|| !in_array((int) $project->entity, array_map('intval', explode(',', getEntity('project'))), true)
							|| $project->restrictedProjectArea($user, 'read') <= 0) { throw new RuntimeException('project_inaccessible'); }
						// Preserve the distinction between a native NULL budget and a zero budget.
						$result = $db->query('SELECT budget_amount FROM '.MAIN_DB_PREFIX.'projet WHERE rowid = '.$projectId.' AND entity = '.(int) $project->entity.($locked ? ' LOCK IN SHARE MODE' : ''));
						if (!$result || !is_object($row = $db->fetch_object($result))) { throw new RuntimeException('technical_error'); }
						if ($row->budget_amount === null || $row->budget_amount === '') {
							$check['reason'] = 'no_project_budget';
						} else {
							$budget = LmdbSupplierOrderLimitPolicy::amount($row->budget_amount);
							if ($budget === null) { throw new RuntimeException('invalid_amount'); }
							$spent = $ledger->projectSpent($projectId, (int) $project->entity, (int) $order->id, $locked, $entity);
							$check['allowed'] = LmdbSupplierOrderLimitPolicy::compare(LmdbSupplierOrderLimitPolicy::add($spent, $amount), $budget) <= 0;
							$check['ceiling'] = $budget;
						}
					}
					// Safe to render/log: no aggregate of potentially inaccessible orders.
					$check['consumed'] = null;
					$check['projected'] = null;
				} else {
					if ($type !== 'order') {
						$mode = $scope['settings']['LMDBSUPPLIERORDERLIMIT_'.strtoupper($type).'_MODE'];
						$check['period'] = LmdbSupplierOrderLimitPolicy::period($type, $mode, $now, date_default_timezone_get());
						$check['consumed'] = $ledger->spent($entity, $subjectId, (int) $order->id, $check['period'], $locked);
						$date = $approvalDate ?? $now;
						$contribution = $date >= $check['period']['start'] && $date < $check['period']['end'] ? $amount : '0';
						$check['projected'] = LmdbSupplierOrderLimitPolicy::add($check['consumed'], $contribution);
					}
					$ceiling = LmdbSupplierOrderLimitPolicy::amount($rule['amount_ht']);
					if ($ceiling === null) { throw new RuntimeException('invalid_amount'); }
					$check['ceiling'] = $ceiling;
					$check['allowed'] = LmdbSupplierOrderLimitPolicy::compare($check['projected'], $ceiling) <= 0;
				}
				if (!$check['allowed']) {
					$check['reason'] = 'amount_over_limit';
					$decision['allowed'] = false;
					$decision['reason'] = 'amount_over_limit';
					$decision['limit_amount_ht'] = $check['ceiling'];
					$decision['limit_source'] = $rule['source'];
					$decision['fk_limit'] = $rule['rowid'];
				}
				$decision['checks'][] = $check;
			}
		} catch (Throwable $e) {
			$decision['allowed'] = false;
			$decision['reason'] = in_array($e->getMessage(), array('object_not_supplier_order','native_permission_missing','invalid_amount','no_limit_found','project_inaccessible','history_incomplete','currency_mismatch'), true) ? $e->getMessage() : 'technical_error';
			dol_syslog(__METHOD__.': '.$decision['reason'], LOG_ERR);
		}
		return $decision;
	}

	/** @param DoliDB $db
	 * @param array{entities:list<int>,users:list<int>,groups:list<int>,settings:array<string,string>,currency:string,transverse:bool} $scope
	 * @return array<string,SelectedRule>
	 */
	private static function rules($db, array $scope, int $userId, int $entity, int $now, bool $locked): array
	{
		$sql = 'SELECT t.rowid,t.entity,t.fk_user,t.fk_usergroup,t.limit_type,t.amount_ht,t.unlimited FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_limit t';
		$sql .= ' WHERE t.entity IN ('.implode(',', $scope['entities']).') AND t.active = 1';
		$sql .= " AND (t.date_start IS NULL OR t.date_start <= '".$db->idate($now)."') AND (t.date_end IS NULL OR t.date_end >= '".$db->idate($now)."')";
		$sql .= ' AND ((t.fk_user = '.$userId.' AND t.fk_usergroup IS NULL) OR (t.fk_user IS NULL AND EXISTS (';
		$sql .= 'SELECT gu.fk_user FROM '.MAIN_DB_PREFIX.'usergroup_user gu INNER JOIN '.MAIN_DB_PREFIX.'usergroup g ON g.rowid = gu.fk_usergroup';
		$sql .= ' WHERE gu.fk_user = '.$userId.' AND gu.fk_usergroup = t.fk_usergroup AND gu.entity IN (0,'.$entity.') AND g.entity IN ('.implode(',', $scope['groups']).'))))';
		$result = $db->query($sql.($locked ? ' LOCK IN SHARE MODE' : ''));
		if (!$result) { throw new RuntimeException('technical_error'); }
		$rows = array();
		while (is_object($row = $db->fetch_object($result))) {
			$rows[] = array('rowid' => (int) $row->rowid,'entity' => (int) $row->entity,'fk_user' => (int) $row->fk_user,'fk_usergroup' => (int) $row->fk_usergroup,'limit_type' => (string) $row->limit_type,'amount_ht' => $row->amount_ht === null ? null : (string) $row->amount_ht,'unlimited' => (int) $row->unlimited);
		}
		return LmdbSupplierOrderLimitPolicy::select($rows, $entity);
	}

	/** @param LimitDecision $decision */
	public static function formatDecisionMessage($decision): string
	{
		global $langs;
		foreach ($decision['checks'] ?? array() as $check) {
			if (!$check['allowed']) {
				$message = $langs->trans('LimitControlDenied', $langs->trans(LmdbSupplierOrderLimitPolicy::TYPES[$check['type']]));
				if ($check['type'] !== 'project_budget') { $message .= ' '.price($check['projected']).' / '.price($check['ceiling']); }
				return $message;
			}
		}
		$keys = array('native_permission_missing' => 'LmdbSupplierOrderLimitNativePermissionMissing', 'no_limit_found' => 'LmdbSupplierOrderLimitNoLimitFound', 'invalid_amount' => 'LmdbSupplierOrderLimitInvalidAmount', 'history_incomplete' => 'LimitHistoryIncomplete', 'project_inaccessible' => 'LimitProjectInaccessible', 'currency_mismatch' => 'LimitCurrencyMismatch');
		return $langs->trans($keys[$decision['reason'] ?? ''] ?? 'LimitTechnicalError');
	}
}
