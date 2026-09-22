<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Descriptor for module LmdbSupplierOrderLimit.
 */
class modLmdbSupplierOrderLimit extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$this->numero = 450025;
		$this->rights_class = 'lmdbsupplierorderlimit';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = 90;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'LmdbSupplierOrderLimitDescription';
		$this->descriptionlong = 'LmdbSupplierOrderLimitDescription';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->rights_class);
		$this->picto = 'supplier_order';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = '';

		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);

		$this->depends = array('modFournisseur');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('lmdbsupplierorderlimit@lmdbsupplierorderlimit');

		$this->config_page_url = array(
			'setup.php@lmdbsupplierorderlimit',
		);

		$this->dirs = array();

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array('ordersuppliercard', 'multicompanyexternalmodulesharing', 'multicompanyexternalmodules', 'multicompanysharingoptions'),
				'entity' => '0',
			),
		);

		$this->const = array();
		$this->tabs = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Read supplier order approval limits';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'limit';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Create or modify supplier order approval limits';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'limit';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Delete supplier order approval limits';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'limit';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Read supplier order approval limit logs';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'log';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Configure supplier order approval limits';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'config';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$this->menus = array();

		if (is_object($langs)) {
			$langs->load('lmdbsupplierorderlimit@lmdbsupplierorderlimit');
		}
	}

	/**
	 * Init module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		global $conf, $langs;
		$result = $this->_load_tables('/lmdbsupplierorderlimit/sql/');
		if ($result <= 0) {
			return -1;
		}

		require_once __DIR__.'/../../class/lmdbsupplierorderlimitmigration.class.php';
		require_once __DIR__.'/../../class/actions_lmdbsupplierorderlimit.class.php';
		try {
			LmdbSupplierOrderLimitMigration::schema($this->db);
			$this->initDefaultConstants();
			$this->persistSharing();
			$this->db->begin();
			$ledger = new LmdbSupplierOrderLimitConsumption($this->db);
			$ambiguous = $ledger->reconcile((int) $conf->entity);
			$this->db->commit();
			if ($ambiguous) { setEventMessages($langs->trans('LimitHistoryIncomplete'), null, 'warnings'); }
		} catch (Throwable $e) {
			if (!empty($this->db->transaction_opened)) { $this->db->rollback(); }
			$this->error = $langs->trans('LimitTechnicalError');
			return -1;
		}

		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Remove module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		require_once __DIR__.'/../../class/actions_lmdbsupplierorderlimit.class.php';
		try { $this->persistSharing(); } catch (Throwable $e) { $this->error = 'LimitTechnicalError'; return -1; }
		$sql = array();
		return $this->_remove($sql, $options);
	}

	/**
	 * Create missing default constants without overwriting existing settings.
	 *
	 * @return void
	 */
	private function initDefaultConstants()
	{
		global $conf;

		$defaults = array(
			'LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE' => array('value' => '1', 'type' => 'yesno'),
			'LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS' => array('value' => '0', 'type' => 'yesno'),
			'LMDBSUPPLIERORDERLIMIT_LOG_DENIED_APPROVALS' => array('value' => '1', 'type' => 'yesno'),
			'LMDBSUPPLIERORDERLIMIT_DAY_MODE' => array('value' => 'civil', 'type' => 'chaine'),
			'LMDBSUPPLIERORDERLIMIT_MONTH_MODE' => array('value' => 'civil', 'type' => 'chaine'),
			'LMDBSUPPLIERORDERLIMIT_YEAR_MODE' => array('value' => 'civil', 'type' => 'chaine'),
			'LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR' => array('value' => 'unlimited', 'type' => 'chaine'),
		);

		foreach ($defaults as $constant => $definition) {
			if (getDolGlobalString($constant, '__lmdbsupplierorderlimit_missing__') !== '__lmdbsupplierorderlimit_missing__') {
				continue;
			}

			if (dolibarr_set_const($this->db, $constant, $definition['value'], $definition['type'], 0, '', (int) $conf->entity) <= 0) { throw new RuntimeException('LimitTechnicalError'); }
		}
	}

	/** Preserve existing sharing choices, both when enabling and disabling. */
	private function persistSharing(): void
	{
		global $conf;
		$raw = getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING', '{}');
		$existing = json_decode($raw === '' ? '{}' : $raw, true);
		if (!is_array($existing)) { throw new RuntimeException('LimitTechnicalError'); }
		$definition = ActionsLmdbSupplierOrderLimit::getMulticompanySharingDefinition();
		$merged = array_replace_recursive($definition, $existing);
		$merged['lmdbsupplierorderlimit']['sharingelements']['lmdbsupplierorderlimit_limit']['enable'] = $definition['lmdbsupplierorderlimit']['sharingelements']['lmdbsupplierorderlimit_limit']['enable'];
		$json = json_encode($merged);
		if ($json === false || dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', $json, 'chaine', 0, '', (int) $conf->entity) <= 0) { throw new RuntimeException('LimitTechnicalError'); }
	}
}
