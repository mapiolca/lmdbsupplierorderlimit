<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/lmdbsupplierorderlimitpolicy.class.php';
require_once __DIR__.'/lmdbsupplierorderlimitscope.class.php';

/** Approval accounting. Never commits a caller's transaction. SQL errors fail closed. */
class LmdbSupplierOrderLimitConsumption
{
	/** @var DoliDB */
	private $db;
	/** @param DoliDB $db */
	public function __construct($db) { $this->db = $db; }

	/** Serialize an owner entity and all affected projects in the same stable order.
	 * @param list<int> $projects
	 */
	public function lock(int $entity, array $projects = array()): void
	{
		// DebugBar delegates transactions but leaves its inherited counter unset (Dolibarr 20+).
		// Read the underlying counter only; keep all queries on the original traced connection.
		$transactionDb = $this->db;
		while ($transactionDb instanceof TraceableDB) {
			$transactionDb = $transactionDb->db;
		}
		if ($entity <= 0 || empty($transactionDb->transaction_opened)) {
			throw new RuntimeException('transaction_required');
		}
		$keys = array('entity:'.$entity);
		foreach ($projects as $id) { if ($id > 0) { $keys[] = 'project:'.$id; } }
		$keys = array_unique($keys);
		sort($keys, SORT_STRING);
		foreach ($keys as $key) {
			$table = MAIN_DB_PREFIX.'lmdbsupplierorderlimit_lock';
			if (!$this->db->query("INSERT IGNORE INTO ".$table." (entity, scope_key) VALUES (".$entity.", '".$this->db->escape($key)."')")) {
				throw new RuntimeException('technical_error');
			}
			$result = $this->db->query("SELECT rowid FROM ".$table." WHERE scope_key = '".$this->db->escape($key)."' FOR UPDATE");
			if (!$result || !is_object($this->db->fetch_object($result))) { throw new RuntimeException('technical_error'); }
		}
	}

	/** @return stdClass|null */
	public function entry(int $entity, int $id, bool $locked = false): ?stdClass
	{
		$result = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption WHERE entity = '.$entity.' AND fk_supplier_order = '.$id.($locked ? ' FOR UPDATE' : ''));
		if (!$result) { throw new RuntimeException('technical_error'); }
		$row = $this->db->fetch_object($result);
		return $row instanceof stdClass ? $row : null;
	}

	/** Current native state, after the native write and before its commit. */
	public function order(int $entity, int $id): ?stdClass
	{
		$result = $this->db->query('SELECT rowid, entity, fk_soc, fk_projet, fk_statut, total_ht, fk_user_approve, fk_user_approve2, date_approve, date_approve2 FROM '.MAIN_DB_PREFIX.'commande_fournisseur WHERE entity = '.$entity.' AND rowid = '.$id.' FOR UPDATE');
		if (!$result) { throw new RuntimeException('technical_error'); }
		$row = $this->db->fetch_object($result);
		return $row instanceof stdClass ? $row : null;
	}

	/** Block periodic controls until the entity has been reconciled, and on ambiguous history. */
	public function assertReady(int $entity, bool $locked = false): void
	{
		$suffix = $locked ? ' LOCK IN SHARE MODE' : '';
		$result = $this->db->query("SELECT reconciled FROM ".MAIN_DB_PREFIX."lmdbsupplierorderlimit_lock WHERE entity = ".$entity." AND scope_key = 'entity:".$entity."'".$suffix);
		if (!$result) { throw new RuntimeException('technical_error'); }
		$row = $this->db->fetch_object($result);
		if (!is_object($row) || !(int) $row->reconciled) { throw new RuntimeException('history_incomplete'); }
		$result = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption WHERE entity = '.$entity.' AND active = 1 AND unresolved = 1'.$suffix);
		if (!$result) { throw new RuntimeException('technical_error'); }
		if (is_object($this->db->fetch_object($result))) { throw new RuntimeException('history_incomplete'); }
	}

	/** @param array{start:int,end:int} $period */
	public function spent(int $entity, int $userId, int $exclude, array $period, bool $locked): string
	{
		$this->assertReady($entity, $locked);
		$sql = 'SELECT snapshot_amount_ht FROM '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption WHERE entity = '.$entity.' AND fk_user = '.$userId.' AND active = 1 AND fk_supplier_order <> '.$exclude;
		$sql .= " AND date_approval >= '".$this->db->idate($period['start'])."' AND date_approval < '".$this->db->idate($period['end'])."'";
		$result = $this->db->query($sql.($locked ? ' LOCK IN SHARE MODE' : ''));
		if (!$result) { throw new RuntimeException('technical_error'); }
		$total = '0';
		while (is_object($row = $this->db->fetch_object($result))) {
			$amount = LmdbSupplierOrderLimitPolicy::amount($row->snapshot_amount_ht);
			if ($amount === null) { throw new RuntimeException('invalid_amount'); }
			$total = LmdbSupplierOrderLimitPolicy::add($total, $amount);
		}
		return $total;
	}

	/** Internal project aggregate: native source covers entities where this module was inactive.
	 * No order identity or amount from this aggregate may be rendered or logged.
	 */
	public function projectSpent(int $project, int $projectEntity, int $exclude, bool $locked, int $orderEntity): string
	{
		$sql = 'SELECT c.total_ht,c.entity FROM '.MAIN_DB_PREFIX.'commande_fournisseur c INNER JOIN '.MAIN_DB_PREFIX.'projet p ON p.rowid = c.fk_projet';
		$sql .= ' WHERE p.rowid = '.$project.' AND p.entity = '.$projectEntity.' AND c.fk_statut IN (2,3,4,5) AND c.rowid <> '.$exclude;
		$result = $this->db->query($sql.($locked ? ' LOCK IN SHARE MODE' : ''));
		if (!$result) { throw new RuntimeException('technical_error'); }
		$total = '0';
		$entities = array($projectEntity, $orderEntity);
		while (is_object($row = $this->db->fetch_object($result))) {
			$entities[] = (int) $row->entity;
			$amount = LmdbSupplierOrderLimitPolicy::amount($row->total_ht);
			if ($amount === null) { throw new RuntimeException('invalid_amount'); }
			$total = LmdbSupplierOrderLimitPolicy::add($total, $amount);
		}
		LmdbSupplierOrderLimitScope::assertCommonCurrency($this->db, $entities);
		return $total;
	}

	/** @param stdClass $order Native SQL row; snapshot is intentionally updated on authorized amendments. */
	public function record(stdClass $order, ?int $userId, ?int $date): void
	{
		$amount = LmdbSupplierOrderLimitPolicy::amount($order->total_ht);
		if ($amount === null) { throw new RuntimeException('invalid_amount'); }
		$unresolved = $userId === null || $date === null ? 1 : 0;
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption (entity,fk_supplier_order,fk_user,fk_project,date_approval,snapshot_amount_ht,active,unresolved) VALUES (';
		$sql .= (int) $order->entity.','.(int) $order->rowid.','.($userId ?? 'NULL').','.((int) $order->fk_projet ?: 'NULL').',';
		$sql .= ($date === null ? 'NULL' : "'".$this->db->idate($date)."'").",'".$this->db->escape($amount)."',1,".$unresolved.')';
		$sql .= ' ON DUPLICATE KEY UPDATE fk_user=VALUES(fk_user),fk_project=VALUES(fk_project),date_approval=VALUES(date_approval),snapshot_amount_ht=VALUES(snapshot_amount_ht),active=1,unresolved=VALUES(unresolved)';
		if (!$this->db->query($sql)) { throw new RuntimeException('technical_error'); }
	}

	public function release(int $entity, int $id): void
	{
		if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption SET active = 0 WHERE entity = '.$entity.' AND fk_supplier_order = '.$id)) { throw new RuntimeException('technical_error'); }
	}

	/** Conservative native history: only a unique latest complete approval is attributable.
	 * @return array{user:?int,date:?int}
	 */
	public function attribution(stdClass $row): array
	{
		$events = array();
		foreach (array('', '2') as $suffix) {
			$uid = (int) $row->{'fk_user_approve'.$suffix};
			$date = $row->{'date_approve'.$suffix};
			if (!$uid && !$date) { continue; }
			$timestamp = $date ? (int) $this->db->jdate($date) : 0;
			if ($uid <= 0 || $timestamp <= 0) { return array('user' => null, 'date' => null); }
			$events[] = array('user' => $uid, 'date' => $timestamp);
		}
		if (!$events) { return array('user' => null, 'date' => null); }
		usort($events, static function ($a, $b) { return $b['date'] <=> $a['date']; });
		if (count($events) === 2 && $events[0]['date'] === $events[1]['date'] && $events[0]['user'] !== $events[1]['user']) { return array('user' => null, 'date' => null); }
		return $events[0];
	}

	/** Rebuild only this owner's registry. Runs no native business trigger or external effect.
	 * Caller starts/ends transaction. Ambiguity is retained, never silently guessed.
	 * @return int Number of ambiguous orders
	 */
	public function reconcile(int $entity): int
	{
		$this->lock($entity);
		$result = $this->db->query('SELECT rowid,entity,fk_projet,total_ht,fk_user_approve,fk_user_approve2,date_approve,date_approve2 FROM '.MAIN_DB_PREFIX.'commande_fournisseur WHERE entity = '.$entity.' AND fk_statut IN (2,3,4,5) ORDER BY rowid FOR UPDATE');
		if (!$result) { throw new RuntimeException('technical_error'); }
		$rows = array();
		while (($row = $this->db->fetch_object($result)) instanceof stdClass) { $rows[] = $row; }
		if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption SET active = 0 WHERE entity = '.$entity)) { throw new RuntimeException('technical_error'); }
		$ambiguous = 0;
		foreach ($rows as $row) {
			$attribution = $this->attribution($row);
			$previous = $this->entry($entity, (int) $row->rowid, true);
			// A previously observed final approval disambiguates same-second native approvals only.
			if ($attribution['user'] === null && $previous && !(int) $previous->unresolved
				&& $previous->date_approval === $row->date_approve && $previous->date_approval === $row->date_approve2
				&& in_array((int) $previous->fk_user, array((int) $row->fk_user_approve, (int) $row->fk_user_approve2), true)) {
				$attribution = array('user' => (int) $previous->fk_user, 'date' => (int) $this->db->jdate($previous->date_approval));
			}
			$this->record($row, $attribution['user'], $attribution['date']);
			if ($attribution['user'] === null) { $ambiguous++; }
		}
		if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."lmdbsupplierorderlimit_lock SET reconciled = 1 WHERE entity = ".$entity." AND scope_key = 'entity:".$entity."'")) { throw new RuntimeException('technical_error'); }
		return $ambiguous;
	}
}
