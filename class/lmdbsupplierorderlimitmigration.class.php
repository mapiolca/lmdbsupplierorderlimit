<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/lmdbsupplierorderlimitconsumption.class.php';

/** Idempotent schema changes. MySQL DDL is deliberately outside the reconciliation transaction. */
class LmdbSupplierOrderLimitMigration
{
	/** @param DoliDB $db */
	public static function schema($db): void
	{
		$table = MAIN_DB_PREFIX.'lmdbsupplierorderlimit_limit';
		$result = $db->query('SHOW COLUMNS FROM '.$table);
		if (!$result) { throw new RuntimeException('LimitTechnicalError'); }
		$columns = array();
		while (is_object($row = $db->fetch_object($result))) { $columns[] = $row->Field; }
		if (!in_array('limit_type', $columns, true) && !$db->query("ALTER TABLE ".$table." ADD limit_type varchar(16) NOT NULL DEFAULT 'order'")) { throw new RuntimeException('LimitTechnicalError'); }
		$result = $db->query('SHOW INDEX FROM '.$table);
		if (!$result) { throw new RuntimeException('LimitTechnicalError'); }
		$indexes = array();
		while (is_object($row = $db->fetch_object($result))) { $indexes[(string) $row->Key_name] = true; }
		foreach (array('uk_lmdbsol_user_type' => 'fk_user', 'uk_lmdbsol_group_type' => 'fk_usergroup') as $name => $target) {
			if (!isset($indexes[$name]) && !$db->query('ALTER TABLE '.$table.' ADD UNIQUE KEY '.$name.' (entity,'.$target.',limit_type)')) { throw new RuntimeException('LimitTechnicalError'); }
		}
		foreach (array('uk_lmdbsupplierorderlimit_limit_user_entity','uk_lmdbsupplierorderlimit_limit_group_entity') as $old) {
			if (isset($indexes[$old]) && !$db->query('ALTER TABLE '.$table.' DROP INDEX '.$old)) { throw new RuntimeException('LimitTechnicalError'); }
		}
	}
}
