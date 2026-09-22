<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once __DIR__.'/../../class/lmdbsupplierorderlimitauthorizer.class.php';
require_once __DIR__.'/../../class/lmdbsupplierorderlimitlog.class.php';
require_once __DIR__.'/../../class/actions_lmdbsupplierorderlimit.class.php';

/** Native lifecycle enforcement, inside the native transaction. */
class InterfaceLmdbSupplierOrderLimitTriggers extends DolibarrTriggers
{
	/** @var string */ public $family = 'supplier';
	/** @var string */ public $description = 'Supplier order approval accounting';
	/** @var string */ public $version = 'development';
	/** @var string */ public $picto = 'supplier_order';

	/** @param DoliDB $db */
	public function __construct($db) { $this->db = $db; }

	/** @param string $action
	 * @param CommonObject $object
	 * @param User $user
	 * @param Translate $langs
	 * @param Conf $conf
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		$isLine = in_array($action, array('LINEORDER_SUPPLIER_CREATE','LINEORDER_SUPPLIER_INSERT','LINEORDER_SUPPLIER_MODIFY','LINEORDER_SUPPLIER_UPDATE','LINEORDER_SUPPLIER_DELETE'), true);
		$mutations = array('ORDER_SUPPLIER_CREATE', 'ORDER_SUPPLIER_MODIFY', 'ORDER_SUPPLIER_DELETE',
			'ORDER_SUPPLIER_VALIDATE', 'ORDER_SUPPLIER_APPROVE', 'ORDER_SUPPLIER_REFUSE', 'ORDER_SUPPLIER_CANCEL',
			'ORDER_SUPPLIER_SUBMIT', 'ORDER_SUPPLIER_RECEIVE', 'ORDER_SUPPLIER_STATUS_DRAFT', 'ORDER_SUPPLIER_STATUS_VALIDATED',
			'ORDER_SUPPLIER_STATUS_APPROVED', 'ORDER_SUPPLIER_STATUS_ORDERED', 'ORDER_SUPPLIER_STATUS_RECEIVED_PARTIALLY',
			'ORDER_SUPPLIER_STATUS_RECEIVED_COMPLETELY', 'ORDER_SUPPLIER_STATUS_CANCELED', 'ORDER_SUPPLIER_STATUS_REFUSED');
		if (!isModEnabled('lmdbsupplierorderlimit') || (!$isLine && !in_array($action, $mutations, true))) { return 0; }
		$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');
		try {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
			$id = $isLine ? (int) ($object->fk_commande ?? 0) : (int) $object->id;
			$order = new CommandeFournisseur($this->db);
			if ($order->fetch($id) <= 0) {
				if ($action !== 'ORDER_SUPPLIER_DELETE' || !lmdbsupplierorderlimitIsSupplierOrderLike($object)) { throw new RuntimeException('technical_error'); }
				$order = $object;
			}
			$entity = (int) $order->entity;
			if ($entity <= 0 || !in_array($entity, array_map('intval', explode(',', getEntity('supplier_order'))), true)) { throw new RuntimeException('native_permission_missing'); }
			$ledger = new LmdbSupplierOrderLimitConsumption($this->db);
			$ledger->lock($entity);
			$previous = $ledger->entry($entity, $id, true);
			$row = $ledger->order($entity, $id);
			$ledger->lock($entity, array((int) ($previous->fk_project ?? 0), (int) ($row->fk_projet ?? 0)));
			if ($isLine) {
				// Native line triggers precede the parent total recalculation. Approved lines must return to draft.
				if ($row && in_array((int) $row->fk_statut, array(2,3,4,5), true)) { throw new RuntimeException('approved_line'); }
				$ledger->release($entity, $id);
				return 0;
			}
			if ($action === 'ORDER_SUPPLIER_DELETE' || !$row || !in_array((int) $row->fk_statut, array(2,3,4,5), true)) {
				if ($action === 'ORDER_SUPPLIER_APPROVE') {
					$level = self::approvalLevel($this->db, $object, $row);
					if (!$user->hasRight('fournisseur', 'commande', $level === 2 ? 'approve2' : 'approuver')) { throw new RuntimeException('native_permission_missing'); }
					$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $order, $level, true, $level === 2);
					if (!$decision['allowed']) { throw new RuntimeException(LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($decision)); }
				}
				$ledger->release($entity, $id);
				return 0;
			}
			// Use the state written by core, not its object (updated only after triggers).
			$order->total_ht = $row->total_ht;
			$order->fk_project = (int) $row->fk_projet;
			$existing = $previous && (int) $previous->active;
			// setStatus() does not execute native approval levels or write their actor/date fields.
			if (!$existing && $action === 'ORDER_SUPPLIER_STATUS_APPROVED') { throw new RuntimeException('native_approval_required'); }
			if (!$existing && $action !== 'ORDER_SUPPLIER_APPROVE') { throw new RuntimeException('history_incomplete'); }
			$amount = LmdbSupplierOrderLimitPolicy::amount($row->total_ht);
			$changed = !$existing || $amount !== LmdbSupplierOrderLimitPolicy::amount($previous->snapshot_amount_ht) || (int) $previous->fk_project !== (int) $row->fk_projet;
			// Repeated native triggers never change the attribution or consume twice.
			if (!$changed) { return 0; }
			$level = $action === 'ORDER_SUPPLIER_APPROVE' ? self::approvalLevel($this->db, $object, $row) : 1;
			$date = $existing && $previous->date_approval ? (int) $this->db->jdate($previous->date_approval) : dol_now();
			if (!$existing && $action === 'ORDER_SUPPLIER_APPROVE') {
				$date = (int) $this->db->jdate($row->{$level === 2 ? 'date_approve2' : 'date_approve'});
				if ($date <= 0) { throw new RuntimeException('history_incomplete'); }
			}
			$subject = $existing ? (int) $previous->fk_user : null;
			if ($existing && ((int) $previous->unresolved || $subject <= 0)) { throw new RuntimeException('history_incomplete'); }
			$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $order, $level, true, true, $date, $subject);
			if (!$decision['allowed']) { throw new RuntimeException(LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($decision)); }
			$ledger->record($row, $subject ?? (int) $user->id, $date);
			LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $order, $decision, 'approval_allowed', 'trigger', 'allowed');
			return 0;
		} catch (Throwable $e) {
			$keys = array('technical_error' => 'LimitTechnicalError','transaction_required' => 'LimitTechnicalError','history_incomplete' => 'LimitHistoryIncomplete','approved_line' => 'LimitApprovedLine','native_permission_missing' => 'LmdbSupplierOrderLimitNativePermissionMissing','native_approval_required' => 'LimitNativeApprovalRequired');
			$this->error = isset($keys[$e->getMessage()]) ? $langs->trans($keys[$e->getMessage()]) : $e->getMessage();
			$this->errors = array($this->error);
			dol_syslog(__METHOD__.': supplier order control refused', LOG_ERR);
			return -1; // Native caller rolls back all database writes, including our ledger.
		} finally {
			if ($action === 'ORDER_SUPPLIER_APPROVE') { ActionsLmdbSupplierOrderLimit::restoreWorkflow(); }
		}
	}

	/** Detect core's changed approval fields; ambiguous calls are refused.
	 * @param DoliDB $db
	 * @param CommonObject $before
	 * @param stdClass|null $after
	 */
	private static function approvalLevel($db, $before, ?stdClass $after): int
	{
		if (!$after) { throw new RuntimeException('technical_error'); }
		foreach (array(2 => '2', 1 => '') as $level => $suffix) {
			$oldUser = (int) ($before->{'user_approve_id'.$suffix} ?? 0);
			$oldDate = $before->{'date_approve'.$suffix} ?? null;
			$oldTime = is_numeric($oldDate) ? (int) $oldDate : ($oldDate ? (int) $db->jdate($oldDate) : 0);
			$newTime = $after->{'date_approve'.$suffix} ? (int) $db->jdate($after->{'date_approve'.$suffix}) : 0;
			if ((int) $after->{'fk_user_approve'.$suffix} > 0 && ($oldUser !== (int) $after->{'fk_user_approve'.$suffix} || $oldTime !== $newTime)) { return $level; }
		}
		throw new RuntimeException('technical_error');
	}
}
