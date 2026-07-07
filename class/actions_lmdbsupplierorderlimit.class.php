<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitauthorizer.class.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlog.class.php');

/**
 * Hook class.
 */
class ActionsLmdbSupplierOrderLimit
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();
	/** @var array<string, mixed> */
	public $results = array();
	/** @var string */
	public $resprints = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Handle supplier order actions before core processing.
	 *
	 * @param array<string, mixed> $parameters  Hook parameters
	 * @param mixed                $object      Hook object
	 * @param string               $action      Current action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (!$this->isSupplierOrderCardContext($parameters) || !isModEnabled('lmdbsupplierorderlimit')) {
			return 0;
		}

		if (!lmdbsupplierorderlimitIsSupplierOrderLike($object)) {
			return 0;
		}

		$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');

		$approveActions = array('approve', 'approve2', 'confirm_approve', 'confirm_approve2');
		$validateActions = array('', 'valid', 'confirm_valid');

		if (in_array($action, $approveActions, true)) {
			$approvalLevel = strpos($action, 'approve2') !== false ? 2 : 1;
			$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, $approvalLevel);
			if (empty($decision['allowed'])) {
				$message = $this->getDeniedMessage($decision);
				setEventMessages($message, null, 'errors');
				LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_denied', 'hook', $message);
				return 1;
			}

			LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_allowed', 'hook', 'allowed');
			return 0;
		}

		if (in_array($action, $validateActions, true) && $user->hasRight('fournisseur', 'commande', 'approuver')) {
			$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 1);
			if (empty($decision['allowed'])) {
				// Runtime-only override: it prevents direct validate+approve for this request without writing SUPPLIER_ORDER_NO_DIRECT_APPROVE in database.
				$conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE = 1;
				if ($action === 'confirm_valid') {
					LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_direct_validate_blocked', 'hook', 'direct validate approval blocked');
				}
			}
		}

		return 0;
	}

	/**
	 * Adjust action buttons when approval is financially refused.
	 *
	 * @param array<string, mixed> $parameters  Hook parameters
	 * @param mixed                $object      Hook object
	 * @param string               $action      Current action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (!$this->isSupplierOrderCardContext($parameters) || !isModEnabled('lmdbsupplierorderlimit')) {
			return 0;
		}

		if (!lmdbsupplierorderlimitIsSupplierOrderLike($object)) {
			return 0;
		}

		$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');
		$langs->load('orders');
		$status = isset($object->statut) ? (int) $object->statut : (isset($object->status) ? (int) $object->status : -1);

		if ($status === 0 && $user->hasRight('fournisseur', 'commande', 'approuver')) {
			$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 1);
			if (empty($decision['allowed'])) {
				$conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE = 1;
			}
			return 0;
		}

		if ($status !== 1) {
			return 0;
		}

		$approvalLevel = $user->hasRight('fournisseur', 'commande', 'approve2') ? 2 : 1;
		$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, $approvalLevel);
		if (!empty($decision['allowed']) || (isset($decision['reason']) && $decision['reason'] === 'native_permission_missing')) {
			return 0;
		}

		$message = $this->getDeniedMessage($decision);
		print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($message).'">'.$this->getNativeApprovalButtonLabel($approvalLevel).'</a>';

		return 1;
	}

	/**
	 * Check hook context.
	 *
	 * @param array<string, mixed> $parameters Hook parameters
	 * @return bool
	 */
	private function isSupplierOrderCardContext($parameters)
	{
		$contexts = isset($parameters['context']) ? explode(':', (string) $parameters['context']) : array();
		return in_array('ordersuppliercard', $contexts, true);
	}

	/**
	 * Return denied message respecting configuration.
	 *
	 * @param array<string, mixed> $decision Decision
	 * @return string
	 */
	private function getDeniedMessage($decision)
	{
		global $langs;

		if (getDolGlobalInt('LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE', 1)) {
			return LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($decision);
		}

		return $langs->trans('LmdbSupplierOrderLimitApprovalDenied');
	}

	/**
	 * Return the native supplier order approval button label.
	 *
	 * @param int $approvalLevel Approval level
	 * @return string
	 */
	private function getNativeApprovalButtonLabel($approvalLevel)
	{
		global $langs;

		return ((int) $approvalLevel === 2) ? $langs->trans('Approve2Order') : $langs->trans('ApproveOrder');
	}
}
