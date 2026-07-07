<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Approval limit log entry.
 */
class LmdbSupplierOrderLimitLog extends CommonObject
{
	/** @var string */
	public $module = 'lmdbsupplierorderlimit';
	/** @var string */
	public $element = 'lmdbsupplierorderlimit_log';
	/** @var string */
	public $table_element = 'lmdbsupplierorderlimit_log';
	/** @var string */
	public $picto = 'list';
	/** @var int */
	public $ismultientitymanaged = 1;

	/** @var int */
	public $rowid;
	/** @var int */
	public $id;
	/** @var int */
	public $entity;
	/** @var string */
	public $event_type;
	/** @var int */
	public $decision = 0;
	/** @var int|null */
	public $fk_supplier_order;
	/** @var int */
	public $fk_user_action;
	/** @var string|null */
	public $order_total_ht;
	/** @var string|null */
	public $limit_amount_ht;
	/** @var int */
	public $limit_unlimited = 0;
	/** @var string|null */
	public $limit_source;
	/** @var int|null */
	public $fk_limit;
	/** @var string */
	public $reason_code;
	/** @var string|null */
	public $origin;
	/** @var string|null */
	public $message;
	/** @var int|string|null */
	public $date_creation;
	/** @var string|null */
	public $ip;
	/** @var string|null */
	public $user_agent;
	/** @var string|null */
	public $user_login;
	/** @var string|null */
	public $user_lastname;
	/** @var string|null */
	public $user_firstname;
	/** @var string|null */
	public $user_photo;
	/** @var int|null */
	public $user_status;
	/** @var string|null */
	public $user_email;
	/** @var int|null */
	public $user_admin;
	/** @var int|null */
	public $user_entity;
	/** @var string|null */
	public $supplier_order_ref;
	/** @var int|null */
	public $supplier_order_status;
	/** @var int|null */
	public $supplier_order_entity;
	/** @var string|null */
	public $supplier_order_total_ht;

	/**
	 * Field definitions.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'notnull' => 1),
		'event_type' => array('type' => 'varchar(64)', 'label' => 'LmdbSupplierOrderLimitEventType', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'decision' => array('type' => 'boolean', 'label' => 'LmdbSupplierOrderLimitDecision', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'fk_supplier_order' => array('type' => 'integer', 'label' => 'LmdbSupplierOrderLimitSupplierOrder', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'fk_user_action' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'order_total_ht' => array('type' => 'price', 'label' => 'LmdbSupplierOrderLimitOrderAmount', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'limit_amount_ht' => array('type' => 'price', 'label' => 'LmdbSupplierOrderLimitApplicableLimit', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'limit_unlimited' => array('type' => 'boolean', 'label' => 'LmdbSupplierOrderLimitUnlimited', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'limit_source' => array('type' => 'varchar(32)', 'label' => 'LmdbSupplierOrderLimitLimitSource', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'fk_limit' => array('type' => 'integer', 'label' => 'LmdbSupplierOrderLimitApplicableLimit', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'reason_code' => array('type' => 'varchar(64)', 'label' => 'LmdbSupplierOrderLimitReason', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'origin' => array('type' => 'varchar(64)', 'label' => 'LmdbSupplierOrderLimitOrigin', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'message' => array('type' => 'text', 'label' => 'LmdbSupplierOrderLimitMessage', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
		'ip' => array('type' => 'varchar(64)', 'label' => 'IP', 'enabled' => 1, 'visible' => -1, 'notnull' => 0),
		'user_agent' => array('type' => 'varchar(255)', 'label' => 'UserAgent', 'enabled' => 1, 'visible' => -1, 'notnull' => 0),
	);

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
	 * Create a log entry.
	 *
	 * @param User $user      User
	 * @param int  $notrigger 1 disables triggers
	 * @return int >0 if OK, -1 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		$this->fk_user_action = !empty($this->fk_user_action) ? (int) $this->fk_user_action : (int) $user->id;
		$this->decision = empty($this->decision) ? 0 : 1;
		$this->limit_unlimited = empty($this->limit_unlimited) ? 0 : 1;

		if (empty($this->event_type) || empty($this->reason_code)) {
			$this->error = 'Missing event type or reason code';
			return -1;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, event_type, decision, fk_supplier_order, fk_user_action, order_total_ht, limit_amount_ht, limit_unlimited, limit_source, fk_limit, reason_code, origin, message, date_creation, ip, user_agent';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).', ';
		$sql .= "'".$this->db->escape((string) $this->event_type)."', ";
		$sql .= ((int) $this->decision).', ';
		$sql .= ($this->fk_supplier_order ? ((int) $this->fk_supplier_order) : 'NULL').', ';
		$sql .= ((int) $this->fk_user_action).', ';
		$sql .= ($this->order_total_ht !== null && $this->order_total_ht !== '' ? "'".$this->db->escape((string) $this->order_total_ht)."'" : 'NULL').', ';
		$sql .= ($this->limit_amount_ht !== null && $this->limit_amount_ht !== '' ? "'".$this->db->escape((string) $this->limit_amount_ht)."'" : 'NULL').', ';
		$sql .= ((int) $this->limit_unlimited).', ';
		$sql .= ($this->limit_source !== null ? "'".$this->db->escape((string) $this->limit_source)."'" : 'NULL').', ';
		$sql .= ($this->fk_limit ? ((int) $this->fk_limit) : 'NULL').', ';
		$sql .= "'".$this->db->escape((string) $this->reason_code)."', ";
		$sql .= ($this->origin !== null ? "'".$this->db->escape((string) $this->origin)."'" : 'NULL').', ';
		$sql .= ($this->message !== null ? "'".$this->db->escape((string) $this->message)."'" : 'NULL').', ';
		$sql .= "'".$this->db->idate(dol_now())."', ";
		$sql .= ($this->ip !== null ? "'".$this->db->escape((string) $this->ip)."'" : 'NULL').', ';
		$sql .= ($this->user_agent !== null ? "'".$this->db->escape((string) $this->user_agent)."'" : 'NULL');
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;
		return $this->id;
	}

	/**
	 * Fetch a log entry.
	 *
	 * @param int $id Row id
	 * @return int 1 if found, 0 if not found, -1 if KO
	 */
	public function fetch($id)
	{
		global $conf;

		$sql = 'SELECT t.rowid, t.entity, t.event_type, t.decision, t.fk_supplier_order, t.fk_user_action,';
		$sql .= ' t.order_total_ht, t.limit_amount_ht, t.limit_unlimited, t.limit_source, t.fk_limit, t.reason_code, t.origin, t.message, t.date_creation, t.ip, t.user_agent';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t';
		$sql .= ' WHERE t.rowid = '.((int) $id);
		$sql .= ' AND t.entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return 0;
		}

		$this->setVarsFromDbObject($obj);
		return 1;
	}

	/**
	 * Fetch list.
	 *
	 * @param int                  $limit   Limit
	 * @param int                  $offset  Offset
	 * @param array<string, mixed> $filters Filters
	 * @param string               $sortfield Sort field
	 * @param string               $sortorder Sort order
	 * @return array<int, LmdbSupplierOrderLimitLog>|int
	 */
	public function fetchAll($limit = 100, $offset = 0, $filters = array(), $sortfield = '', $sortorder = '')
	{
		global $conf;

		$records = array();
		$sql = 'SELECT t.rowid, t.entity, t.event_type, t.decision, t.fk_supplier_order, t.fk_user_action,';
		$sql .= ' t.order_total_ht, t.limit_amount_ht, t.limit_unlimited, t.limit_source, t.fk_limit, t.reason_code, t.origin, t.message, t.date_creation, t.ip, t.user_agent,';
		$sql .= ' u.login AS user_login, u.lastname AS user_lastname, u.firstname AS user_firstname, u.photo AS user_photo,';
		$sql .= ' u.statut AS user_status, u.email AS user_email, u.admin AS user_admin, u.entity AS user_entity,';
		$sql .= ' cf.ref AS supplier_order_ref, cf.fk_statut AS supplier_order_status, cf.entity AS supplier_order_entity, cf.total_ht AS supplier_order_total_ht';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = t.fk_user_action';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur AS cf ON cf.rowid = t.fk_supplier_order';
		$sql .= ' WHERE t.entity = '.((int) $conf->entity);
		$sql .= $this->buildWhereFromFilters($filters);
		$sql .= $this->buildOrderBy($sortfield, $sortorder);
		$sql .= $this->db->plimit((int) $limit, (int) $offset);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while (is_object($obj = $this->db->fetch_object($resql))) {
			$record = new self($this->db);
			$record->setVarsFromDbObject($obj);
			$records[] = $record;
		}

		return $records;
	}

	/**
	 * Count list.
	 *
	 * @param array<string, mixed> $filters Filters
	 * @return int
	 */
	public function countAll($filters = array())
	{
		global $conf;

		$sql = 'SELECT COUNT(t.rowid) AS nb';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur AS cf ON cf.rowid = t.fk_supplier_order';
		$sql .= ' WHERE t.entity = '.((int) $conf->entity);
		$sql .= $this->buildWhereFromFilters($filters);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? (int) $obj->nb : 0;
	}

	/**
	 * Log an authorizer decision if configuration allows it.
	 *
	 * @param DoliDB               $db        Database handler
	 * @param User                 $user      User
	 * @param mixed                $order     Supplier order
	 * @param array<string, mixed> $decision  Decision array
	 * @param string               $eventType Event type
	 * @param string               $origin    Origin
	 * @param string               $message   Message
	 * @param bool                 $force     Force logging regardless approval log options
	 * @return int 0 if skipped, >0 if logged, -1 if KO
	 */
	public static function createFromDecision($db, $user, $order, $decision, $eventType, $origin, $message = '', $force = false)
	{
		global $conf;

		$allowed = !empty($decision['allowed']);
		if (!$force) {
			if ($allowed && !getDolGlobalInt('LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS', 0)) {
				return 0;
			}
			if (!$allowed && !getDolGlobalInt('LMDBSUPPLIERORDERLIMIT_LOG_DENIED_APPROVALS', 1)) {
				return 0;
			}
		}

		$log = new self($db);
		$log->entity = isset($order->entity) ? (int) $order->entity : (int) $conf->entity;
		$log->event_type = $eventType;
		$log->decision = $allowed ? 1 : 0;
		$isApprovalEvent = strpos($eventType, 'approval_') === 0;
		$log->fk_supplier_order = $isApprovalEvent ? (isset($order->id) ? (int) $order->id : (isset($order->rowid) ? (int) $order->rowid : null)) : null;
		$log->fk_user_action = (int) $user->id;
		$log->order_total_ht = isset($decision['order_amount_ht']) && $decision['order_amount_ht'] !== null ? (string) $decision['order_amount_ht'] : null;
		$log->limit_amount_ht = isset($decision['limit_amount_ht']) && $decision['limit_amount_ht'] !== null ? (string) $decision['limit_amount_ht'] : null;
		$log->limit_unlimited = !empty($decision['limit_unlimited']) ? 1 : 0;
		$log->limit_source = isset($decision['limit_source']) && $decision['limit_source'] !== null ? (string) $decision['limit_source'] : null;
		$log->fk_limit = isset($decision['fk_limit']) && $decision['fk_limit'] !== null ? (int) $decision['fk_limit'] : null;
		$log->reason_code = isset($decision['reason']) ? (string) $decision['reason'] : 'technical_error';
		$log->origin = $origin;
		$log->message = $message !== '' ? $message : $log->reason_code;
		$log->ip = function_exists('getUserRemoteIP') ? getUserRemoteIP() : null;
		$log->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

		return $log->create($user, 1);
	}

	/**
	 * Set object fields from database row.
	 *
	 * @param stdClass $obj Database row
	 * @return void
	 */
	private function setVarsFromDbObject($obj)
	{
		$this->rowid = (int) $obj->rowid;
		$this->id = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->event_type = (string) $obj->event_type;
		$this->decision = (int) $obj->decision;
		$this->fk_supplier_order = $obj->fk_supplier_order !== null ? (int) $obj->fk_supplier_order : null;
		$this->fk_user_action = (int) $obj->fk_user_action;
		$this->order_total_ht = $obj->order_total_ht !== null ? (string) $obj->order_total_ht : null;
		$this->limit_amount_ht = $obj->limit_amount_ht !== null ? (string) $obj->limit_amount_ht : null;
		$this->limit_unlimited = (int) $obj->limit_unlimited;
		$this->limit_source = $obj->limit_source !== null ? (string) $obj->limit_source : null;
		$this->fk_limit = $obj->fk_limit !== null ? (int) $obj->fk_limit : null;
		$this->reason_code = (string) $obj->reason_code;
		$this->origin = $obj->origin !== null ? (string) $obj->origin : null;
		$this->message = $obj->message !== null ? (string) $obj->message : null;
		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->ip = $obj->ip !== null ? (string) $obj->ip : null;
		$this->user_agent = $obj->user_agent !== null ? (string) $obj->user_agent : null;
		$this->user_login = property_exists($obj, 'user_login') && $obj->user_login !== null ? (string) $obj->user_login : null;
		$this->user_lastname = property_exists($obj, 'user_lastname') && $obj->user_lastname !== null ? (string) $obj->user_lastname : null;
		$this->user_firstname = property_exists($obj, 'user_firstname') && $obj->user_firstname !== null ? (string) $obj->user_firstname : null;
		$this->user_photo = property_exists($obj, 'user_photo') && $obj->user_photo !== null ? (string) $obj->user_photo : null;
		$this->user_status = property_exists($obj, 'user_status') && $obj->user_status !== null ? (int) $obj->user_status : null;
		$this->user_email = property_exists($obj, 'user_email') && $obj->user_email !== null ? (string) $obj->user_email : null;
		$this->user_admin = property_exists($obj, 'user_admin') && $obj->user_admin !== null ? (int) $obj->user_admin : null;
		$this->user_entity = property_exists($obj, 'user_entity') && $obj->user_entity !== null ? (int) $obj->user_entity : null;
		$this->supplier_order_ref = property_exists($obj, 'supplier_order_ref') && $obj->supplier_order_ref !== null ? (string) $obj->supplier_order_ref : null;
		$this->supplier_order_status = property_exists($obj, 'supplier_order_status') && $obj->supplier_order_status !== null ? (int) $obj->supplier_order_status : null;
		$this->supplier_order_entity = property_exists($obj, 'supplier_order_entity') && $obj->supplier_order_entity !== null ? (int) $obj->supplier_order_entity : null;
		$this->supplier_order_total_ht = property_exists($obj, 'supplier_order_total_ht') && $obj->supplier_order_total_ht !== null ? (string) $obj->supplier_order_total_ht : null;
	}

	/**
	 * Build safe list ORDER BY clause.
	 *
	 * @param string $sortfield Sort field
	 * @param string $sortorder Sort order
	 * @return string
	 */
	private function buildOrderBy($sortfield, $sortorder)
	{
		$allowedSortFields = array(
			't.rowid' => 't.rowid',
			'u.lastname' => 'u.lastname',
			'u.firstname' => 'u.firstname',
			'u.login' => 'u.login',
			'cf.ref' => 'cf.ref',
			't.decision' => 't.decision',
			't.event_type' => 't.event_type',
			't.reason_code' => 't.reason_code',
			't.date_creation' => 't.date_creation',
			't.message' => 't.message',
		);

		$orderBy = array();
		$sortFields = array_map('trim', explode(',', $sortfield));
		$sortOrders = array_map('trim', explode(',', strtoupper($sortorder)));

		foreach ($sortFields as $key => $field) {
			if (!isset($allowedSortFields[$field])) {
				continue;
			}

			$order = isset($sortOrders[$key]) ? $sortOrders[$key] : (isset($sortOrders[0]) ? $sortOrders[0] : 'ASC');
			$order = $order === 'ASC' ? 'ASC' : 'DESC';
			$orderBy[] = $allowedSortFields[$field].' '.$order;
		}

		if (empty($orderBy)) {
			$orderBy[] = 't.date_creation DESC';
		}

		$orderBy[] = 't.rowid DESC';

		return ' ORDER BY '.implode(', ', $orderBy);
	}

	/**
	 * Build SQL filters.
	 *
	 * @param array<string, mixed> $filters Filters
	 * @return string
	 */
	private function buildWhereFromFilters($filters)
	{
		$sql = '';

		if (!empty($filters['fk_user_action'])) {
			$sql .= ' AND t.fk_user_action = '.((int) $filters['fk_user_action']);
		}
		if (!empty($filters['supplier_order'])) {
			$searchSupplierOrder = (string) $filters['supplier_order'];
			$sql .= " AND (cf.ref LIKE '%".$this->db->escape($searchSupplierOrder)."%'";
			if (ctype_digit($searchSupplierOrder)) {
				$sql .= ' OR t.fk_supplier_order = '.((int) $searchSupplierOrder);
			}
			$sql .= ')';
		}
		if (isset($filters['decision']) && $filters['decision'] !== '' && $filters['decision'] !== null) {
			$sql .= ' AND t.decision = '.((int) $filters['decision']);
		}
		if (!empty($filters['reason_code'])) {
			$sql .= " AND t.reason_code = '".$this->db->escape((string) $filters['reason_code'])."'";
		}
		if (!empty($filters['event_type'])) {
			$sql .= " AND t.event_type = '".$this->db->escape((string) $filters['event_type'])."'";
		}
		if (!empty($filters['date_start'])) {
			$sql .= " AND t.date_creation >= '".$this->db->idate((int) $filters['date_start'])."'";
		}
		if (!empty($filters['date_end'])) {
			$sql .= " AND t.date_creation <= '".$this->db->idate((int) $filters['date_end'])."'";
		}

		return $sql;
	}
}
