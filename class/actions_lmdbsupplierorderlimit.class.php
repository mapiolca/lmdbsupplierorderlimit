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
	/** Original core configuration values are kept unmodified for exact restoration.
	 * @var array<string,array{exists:bool,value:mixed,assigned:int|string}>
	 */
	private static $workflow = array();
	/** @var int */
	private static $workflowOrder = 0;

	/** Restore temporary settings before another order can be processed. */
	public static function restoreWorkflow(): void
	{
		global $conf;
		foreach (self::$workflow as $key => $state) {
			if (($conf->global->{$key} ?? null) !== $state['assigned']) { continue; }
			if ($state['exists']) { $conf->global->{$key} = $state['value']; } else { unset($conf->global->{$key}); }
		}
		self::$workflow = array();
		self::$workflowOrder = 0;
	}

	/**
	 * @param CommandeFournisseur $order
	 * @param int|string $value
	 */
	private function scopeWorkflow($order, string $key, $value): void
	{
		global $conf;
		if (self::$workflowOrder !== (int) $order->id) { self::restoreWorkflow(); }
		if (!self::$workflow) { register_shutdown_function(array(self::class, 'restoreWorkflow')); }
		self::$workflowOrder = (int) $order->id;
		if (!isset(self::$workflow[$key])) { self::$workflow[$key] = array('exists' => property_exists($conf->global, $key), 'value' => $conf->global->{$key} ?? null, 'assigned' => $value); }
		self::$workflow[$key]['assigned'] = $value;
		$conf->global->{$key} = $value;
	}

	/** Single native Multicompany definition, also persisted by the descriptor.
	 * @return array<string,array{sharingelements:array<string,array{type:string,icon:string,lang:string,tooltip:string,enable:string,input:array{global:array{showhide:bool,hide:bool,del:bool}}}>,sharingmodulename:array<string,string>,dictionary:array{}}>
	 */
	public static function getMulticompanySharingDefinition(): array
	{
		return array('lmdbsupplierorderlimit' => array(
			'sharingelements' => array('lmdbsupplierorderlimit_limit' => array('type' => 'element', 'icon' => 'supplier_order', 'lang' => 'lmdbsupplierorderlimit@lmdbsupplierorderlimit', 'tooltip' => 'LimitSharingInfo', 'enable' => 'isModEnabled("lmdbsupplierorderlimit")', 'input' => array('global' => array('showhide' => true, 'hide' => true, 'del' => true)))),
			'sharingmodulename' => array('lmdbsupplierorderlimit_limit' => 'lmdbsupplierorderlimit'), 'dictionary' => array()));
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @param mixed $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}
	/**
	 * @param array<string,mixed> $parameters
	 * @param mixed $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}
	/**
	 * @param array<string,mixed> $parameters
	 * @param mixed $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}
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
		self::restoreWorkflow();

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

			if ($approvalLevel === 1) {
				$this->forceNativeSecondLevelApprovalIfNeeded($object);
			}

			return 0;
		}

		if (in_array($action, $validateActions, true) && $user->hasRight('fournisseur', 'commande', 'approuver')) {
			$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 1);
			if (empty($decision['allowed'])) {
				// Runtime-only override: it prevents direct validate+approve for this request without writing SUPPLIER_ORDER_NO_DIRECT_APPROVE in database.
				$this->scopeWorkflow($object, 'SUPPLIER_ORDER_NO_DIRECT_APPROVE', 1);
				if ($action === 'confirm_valid') {
					LmdbSupplierOrderLimitLog::createFromDecision($this->db, $user, $object, $decision, 'approval_direct_validate_blocked', 'hook', 'direct validate approval blocked');
				}
			} else {
				$this->forceNativeSecondLevelApprovalIfNeeded($object);
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
		if (self::$workflowOrder && (!is_object($object) || (int) $object->id !== self::$workflowOrder)) { self::restoreWorkflow(); }

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
				$this->scopeWorkflow($object, 'SUPPLIER_ORDER_NO_DIRECT_APPROVE', 1);
			} else {
				$this->forceNativeSecondLevelApprovalIfNeeded($object);
			}
			return 0;
		}

		if ($status !== 1) {
			return 0;
		}

		if ($user->hasRight('fournisseur', 'commande', 'approve2')) {
			$this->forceNativeSecondLevelApprovalIfNeeded($object);
			$secondLevelDecision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 2);
			if (empty($secondLevelDecision['allowed']) && (!isset($secondLevelDecision['reason']) || $secondLevelDecision['reason'] !== 'native_permission_missing')) {
				$this->printDisableSecondLevelApprovalScript($this->getDeniedMessage($secondLevelDecision));
			}
			return 0;
		}

		$decision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 1);
		if (!empty($decision['allowed']) || (isset($decision['reason']) && $decision['reason'] === 'native_permission_missing')) {
			return 0;
		}

		$message = $this->getDeniedMessage($decision);
		print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($message).'">'.$this->getNativeApprovalButtonLabel(1).'</a>';

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

	/**
	 * Force Dolibarr native second approval path for this request when module limit blocks level 2.
	 *
	 * @param mixed $object Supplier order object
	 * @return void
	 */
	private function forceNativeSecondLevelApprovalIfNeeded($object)
	{
		global $conf, $user;

		$secondLevelDecision = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($this->db, $user, $object, 2);
		$reason = isset($secondLevelDecision['reason']) ? (string) $secondLevelDecision['reason'] : '';
		if (!empty($secondLevelDecision['allowed']) || $reason === 'native_permission_missing') {
			return;
		}

		// Runtime-only override: it makes core approve() keep status validated after first approval without changing persisted Dolibarr settings.
		$this->scopeWorkflow($object, 'SUPPLIER_ORDER_3_STEPS_TO_BE_APPROVED', $this->getNativeSecondLevelThreshold($object));
	}

	/**
	 * Return a threshold that makes the current order enter the native second approval workflow.
	 *
	 * @param mixed $object Supplier order object
	 * @return string
	 */
	private function getNativeSecondLevelThreshold($object)
	{
		$orderAmount = is_object($object) && isset($object->total_ht) ? LmdbSupplierOrderLimitPolicy::amount($object->total_ht) : null;
		if ($orderAmount !== null && LmdbSupplierOrderLimitPolicy::compare($orderAmount, '0') > 0) {
			return $orderAmount;
		}

		// A nonzero, negative sentinel also routes a zero-total order through native level two.
		return '-1';
	}

	/**
	 * Disable the native second-level approval button without replacing the whole native action bar.
	 *
	 * @param string $message Tooltip message
	 * @return void
	 */
	private function printDisableSecondLevelApprovalScript($message)
	{
		$messageJson = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		if ($messageJson === false) {
			$messageJson = '""';
		}

		print '<script>
jQuery(function() {
	var deniedMessage = '.$messageJson.';
	jQuery(\'a.butAction[href*="action=approve2"]\').each(function() {
		jQuery(this)
			.removeClass(\'butAction\')
			.addClass(\'butActionRefused classfortooltip\')
			.attr(\'href\', \'#\')
			.attr(\'aria-disabled\', \'true\')
			.attr(\'title\', deniedMessage)
			.on(\'click.lmdbsupplierorderlimit\', function(event) {
				event.preventDefault();
				return false;
			});
	});
});
</script>';
	}
}
