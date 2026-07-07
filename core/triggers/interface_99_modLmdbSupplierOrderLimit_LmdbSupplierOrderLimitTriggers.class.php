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
	/** @var int|null */
	private $detectedSupplierOrderStatus;

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

		$approvalLevel = $this->detectSupplierOrderApprovalLevel($object);
		if ($approvalLevel < 1) {
			$message = $langs->trans('LmdbSupplierOrderLimitApprovalLevelDetectionError');
			if (!empty($this->error)) {
				$message .= ' '.$this->error;
			}
			$this->error = $message;
			dol_syslog(__METHOD__.': '.$message, LOG_ERR);
			return -1;
		}

		$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, $approvalLevel);
		if (empty($decision['allowed'])) {
			$message = LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($decision);
			$this->error = $message;
			LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_trigger_denied', 'trigger', $message);
			return -1;
		}

		if ($approvalLevel === 1 && $this->isDetectedSupplierOrderAccepted()) {
			$secondLevelDecision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 2);
			$secondLevelReason = isset($secondLevelDecision['reason']) ? (string) $secondLevelDecision['reason'] : '';
			if (empty($secondLevelDecision['allowed']) && $secondLevelReason !== 'native_permission_missing') {
				$message = LmdbSupplierOrderLimitAuthorizer::formatDecisionMessage($secondLevelDecision);
				$this->error = $message;
				LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $secondLevelDecision, 'approval_trigger_denied', 'trigger', $message);
				return -1;
			}
		}

		LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_allowed', 'trigger', 'allowed');

		return 0;
	}

	/**
	 * Detect if the native approval call updated first or second approval fields.
	 *
	 * @param mixed $object Supplier order object
	 * @return int 1 for first level, 2 for second level, -1 on technical failure
	 */
	private function detectSupplierOrderApprovalLevel($object)
	{
		$id = isset($object->id) ? (int) $object->id : (isset($object->rowid) ? (int) $object->rowid : 0);
		if ($id <= 0) {
			$this->error = 'Missing supplier order id';
			return -1;
		}

		$sql = 'SELECT c.fk_user_approve, c.fk_user_approve2, c.date_approve, c.date_approve2, c.fk_statut';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'commande_fournisseur AS c';
		$sql .= ' WHERE c.rowid = '.((int) $id);
		if (isset($object->entity) && (int) $object->entity > 0) {
			$sql .= ' AND c.entity = '.((int) $object->entity);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$row = $this->db->fetch_object($resql);
		if (!is_object($row)) {
			$this->error = 'Supplier order row not found';
			return -1;
		}

		$this->detectedSupplierOrderStatus = isset($row->fk_statut) ? (int) $row->fk_statut : null;
		$currentFirstUserId = !empty($row->fk_user_approve) ? (int) $row->fk_user_approve : null;
		$currentSecondUserId = !empty($row->fk_user_approve2) ? (int) $row->fk_user_approve2 : null;
		$currentFirstDate = $this->normalizeDateForComparison($row->date_approve);
		$currentSecondDate = $this->normalizeDateForComparison($row->date_approve2);

		$previousFirstUserId = self::getObjectIntPropertyState($object, array('user_approve_id', 'fk_user_approve'));
		$previousSecondUserId = self::getObjectIntPropertyState($object, array('user_approve_id2', 'fk_user_approve2'));
		$previousFirstDate = $this->getObjectDatePropertyState($object, array('date_approve'));
		$previousSecondDate = $this->getObjectDatePropertyState($object, array('date_approve2'));

		$secondLevelChanged = self::hasStateChanged($previousSecondUserId, $currentSecondUserId) || self::hasStateChanged($previousSecondDate, $currentSecondDate);
		if ($secondLevelChanged) {
			return 2;
		}

		$firstLevelChanged = self::hasStateChanged($previousFirstUserId, $currentFirstUserId) || self::hasStateChanged($previousFirstDate, $currentFirstDate);
		if ($firstLevelChanged) {
			return 1;
		}

		$this->error = 'Unable to detect supplier order approval level';
		return -1;
	}

	/**
	 * Check if the current SQL row already reached the native accepted supplier order status.
	 *
	 * @return bool
	 */
	private function isDetectedSupplierOrderAccepted()
	{
		if ($this->detectedSupplierOrderStatus === null) {
			return false;
		}

		if (class_exists('CommandeFournisseur') && defined('CommandeFournisseur::STATUS_ACCEPTED')) {
			return (int) $this->detectedSupplierOrderStatus === (int) constant('CommandeFournisseur::STATUS_ACCEPTED');
		}

		return false;
	}

	/**
	 * Return an integer object property state.
	 *
	 * @param mixed              $object     Object to inspect
	 * @param array<int, string> $properties Candidate property names
	 * @return array{exists: bool, value: int|null}
	 */
	private static function getObjectIntPropertyState($object, $properties)
	{
		$vars = is_object($object) ? get_object_vars($object) : array();
		foreach ($properties as $property) {
			if (!array_key_exists($property, $vars)) {
				continue;
			}

			$value = $vars[$property];
			if ($value === null || $value === '') {
				return array('exists' => true, 'value' => null);
			}
			if (!is_scalar($value)) {
				return array('exists' => true, 'value' => null);
			}

			$intValue = (int) $value;
			return array('exists' => true, 'value' => $intValue > 0 ? $intValue : null);
		}

		return array('exists' => false, 'value' => null);
	}

	/**
	 * Return a date object property state normalized to a timestamp.
	 *
	 * @param mixed              $object     Object to inspect
	 * @param array<int, string> $properties Candidate property names
	 * @return array{exists: bool, value: int|null}
	 */
	private function getObjectDatePropertyState($object, $properties)
	{
		$vars = is_object($object) ? get_object_vars($object) : array();
		foreach ($properties as $property) {
			if (!array_key_exists($property, $vars)) {
				continue;
			}

			return array('exists' => true, 'value' => $this->normalizeDateForComparison($vars[$property]));
		}

		return array('exists' => false, 'value' => null);
	}

	/**
	 * Normalize a Dolibarr date value to a timestamp.
	 *
	 * @param mixed $value Date value
	 * @return int|null
	 */
	private function normalizeDateForComparison($value)
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (is_int($value)) {
			return $value > 0 ? $value : null;
		}

		if (is_float($value)) {
			$timestamp = (int) $value;
			return $timestamp > 0 ? $timestamp : null;
		}

		if (is_string($value)) {
			$value = trim($value);
			if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
				return null;
			}

			if (ctype_digit($value)) {
				$timestamp = (int) $value;
				return $timestamp > 0 ? $timestamp : null;
			}

			$timestamp = (int) $this->db->jdate($value);
			return $timestamp > 0 ? $timestamp : null;
		}

		return null;
	}

	/**
	 * Check if a known previous state changed to a non-empty current value.
	 *
	 * @param array{exists: bool, value: int|null} $previousState Previous state
	 * @param int|null                            $currentValue  Current value
	 * @return bool
	 */
	private static function hasStateChanged($previousState, $currentValue)
	{
		if (empty($previousState['exists']) || $currentValue === null) {
			return false;
		}

		return $previousState['value'] === null || (int) $previousState['value'] !== (int) $currentValue;
	}
}
