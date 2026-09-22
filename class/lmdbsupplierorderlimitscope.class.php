<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Owner-entity configuration and native Multicompany sharing, without switching the current user context. */
class LmdbSupplierOrderLimitScope
{
	/** Financial scopes cannot silently add or compare different base currencies.
	 * @param DoliDB $db
	 * @param list<int> $entities
	 */
	public static function assertCommonCurrency($db, array $entities): void
	{
		global $conf;
		if (!isModEnabled('multicompany')) { return; }
		dol_include_once('/multicompany/class/dao_multicompany.class.php');
		if (!class_exists('DaoMulticompany')) { throw new RuntimeException('sharing_unavailable'); }
		$dao = new DaoMulticompany($db);
		$global = $dao->getEntityConfig(0, 'MAIN_MONNAIE');
		if (!is_array($global)) { throw new RuntimeException('technical_error'); }
		$currencies = array();
		foreach (array_unique($entities) as $entity) {
			if ($entity === (int) $conf->entity) { $currency = getDolGlobalString('MAIN_MONNAIE', (string) $conf->currency); }
			else {
				$local = $dao->getEntityConfig($entity, 'MAIN_MONNAIE');
				if (!is_array($local)) { throw new RuntimeException('technical_error'); }
				$currency = (string) ($local['MAIN_MONNAIE'] ?? $global['MAIN_MONNAIE'] ?? '');
			}
			if ($currency === '') { throw new RuntimeException('currency_mismatch'); }
			$currencies[$currency] = true;
		}
		if (count($currencies) > 1) { throw new RuntimeException('currency_mismatch'); }
	}

	/**
	 * @param DoliDB $db
	 * @return array{entities:list<int>,users:list<int>,groups:list<int>,settings:array<string,string>,currency:string,transverse:bool}
	 */
	public static function context($db, int $entity): array
	{
		global $conf;
		if ($entity <= 0) {
			throw new RuntimeException('invalid_entity');
		}
		$defaults = array(
			'LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR' => 'unlimited',
			'LMDBSUPPLIERORDERLIMIT_DAY_MODE' => 'civil',
			'LMDBSUPPLIERORDERLIMIT_MONTH_MODE' => 'civil',
			'LMDBSUPPLIERORDERLIMIT_YEAR_MODE' => 'civil',
		);
		$settings = array();
		$entities = array($entity);
		$currency = (string) $conf->currency;
		$transverse = getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE');
		if ($entity === (int) $conf->entity) {
			foreach ($defaults as $name => $default) {
				$settings[$name] = getDolGlobalString($name, $default);
			}
			$entities = array_values(array_unique(array_map('intval', explode(',', getEntity('lmdbsupplierorderlimit_limit')))));
		} else {
			if (!isModEnabled('multicompany')) {
				throw new RuntimeException('invalid_entity');
			}
			dol_include_once('/multicompany/class/dao_multicompany.class.php');
			if (!class_exists('DaoMulticompany')) {
				throw new RuntimeException('sharing_unavailable');
			}
			$dao = new DaoMulticompany($db);
			if ($dao->fetch($entity) <= 0 || !$dao->active) {
				throw new RuntimeException('invalid_entity');
			}
			$options = is_array($dao->options) ? $dao->options : array();
			$global = $dao->getEntityConfig(0);
			$local = $dao->getEntityConfig($entity);
			if (!is_array($global) || !is_array($local)) {
				throw new RuntimeException('technical_error');
			}
			$config = array_replace($global, $local);
			$currency = isset($config['MAIN_MONNAIE']) ? (string) $config['MAIN_MONNAIE'] : $currency;
			if (empty($config['MAIN_MODULE_LMDBSUPPLIERORDERLIMIT'])) {
				throw new RuntimeException('owner_module_disabled');
			}
			foreach ($defaults as $name => $default) {
				$settings[$name] = isset($config[$name]) ? (string) $config[$name] : $default;
			}
			$transverse = !empty($config['MULTICOMPANY_TRANSVERSE_MODE']);
			if (!empty($config['MULTICOMPANY_SHARINGS_ENABLED']) && !empty($config['MULTICOMPANY_LMDBSUPPLIERORDERLIMIT_LIMIT_SHARING_ENABLED'])) {
				$shared = $options['sharings']['lmdbsupplierorderlimit_limit'] ?? array();
				if (!is_array($shared)) {
					throw new RuntimeException('sharing_unavailable');
				}
				foreach ($shared as $id) {
					if ((int) $id <= 0 || $dao->fetch((int) $id) <= 0) { throw new RuntimeException('sharing_unavailable'); }
					if ($dao->active) {
						$entities[] = (int) $id;
					}
				}
			}
		}
		foreach (array('DAY', 'MONTH', 'YEAR') as $period) {
			if (!in_array($settings['LMDBSUPPLIERORDERLIMIT_'.$period.'_MODE'], array('civil', 'rolling'), true)) {
				throw new RuntimeException('invalid_period');
			}
		}
		if (!in_array($settings['LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR'], array('deny', 'unlimited'), true)) {
			throw new RuntimeException('invalid_rule');
		}
		return array('entities' => array_values(array_unique(array_filter($entities, static function ($id) { return $id > 0; }))),
			'users' => array(0, $transverse ? 1 : $entity), 'groups' => array(0, $entity), 'settings' => $settings, 'currency' => $currency, 'transverse' => (bool) $transverse);
	}

	/**
	 * Accessible native labels for the rule list; never infer entity names from identifiers.
	 * @param DoliDB $db
	 * @return array<int,string>
	 */
	public static function labels($db): array
	{
		global $conf;
		$context = self::context($db, (int) $conf->entity);
		if (!isModEnabled('multicompany')) {
			return array();
		}
		dol_include_once('/multicompany/class/dao_multicompany.class.php');
		if (!class_exists('DaoMulticompany')) {
			throw new RuntimeException('sharing_unavailable');
		}
		$dao = new DaoMulticompany($db);
		$labels = array();
		foreach ($context['entities'] as $id) {
			if ($dao->fetch($id) <= 0 || !$dao->active) {
				throw new RuntimeException('sharing_unavailable');
			}
			$labels[$id] = (string) $dao->label;
		}
		return $labels;
	}
}
