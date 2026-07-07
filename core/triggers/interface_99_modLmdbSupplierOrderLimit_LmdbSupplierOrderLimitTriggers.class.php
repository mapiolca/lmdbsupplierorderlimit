<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitauthorizer.class.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlog.class.php');

/**
 * Trigger handler for supplier order approval limits.
 */
class InterfaceLmdbSupplierOrderLimitTriggers extends DolibarrTriggers
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $family = 'supplier';
	/** @var string */
	public $description = 'Supplier order approval limit triggers';
	/** @var string */
	public $version = '1.0.0';
	/** @var string */
	public $picto = 'supplier_order';

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
	 * Run trigger.
	 *
	 * @param string $action Trigger action
	 * @param mixed  $object Object
	 * @param User   $user   User
	 * @param Translate $langs Language handler
	 * @param Conf   $conf   Conf
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if ($action !== 'ORDER_SUPPLIER_APPROVE') {
			return 0;
		}

		if (!isModEnabled('lmdbsupplierorderlimit')) {
			return 0;
		}

		$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');

		if (!lmdbsupplierorderlimitIsSupplierOrderLike($object)) {
			return 0;
		}

		$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 1);
		if (empty($decision['allowed'])) {
			$message = LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($decision);
			$this->error = $message;
			LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_trigger_denied', 'trigger', $message);
			return -1;
		}

		LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_allowed', 'trigger', 'allowed');

		return 0;
	}
}
