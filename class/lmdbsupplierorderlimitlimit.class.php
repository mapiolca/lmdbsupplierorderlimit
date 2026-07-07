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
 * Approval limit rule.
 */
class LmdbSupplierOrderLimitLimit extends CommonObject
{
	/**
	 * @var string Module key
	 */
	public $module = 'lmdbsupplierorderlimit';

	/**
	 * @var string Object element
	 */
	public $element = 'lmdbsupplierorderlimit_limit';

	/**
	 * @var string Table element
	 */
	public $table_element = 'lmdbsupplierorderlimit_limit';

	/**
	 * @var string Picto
	 */
	public $picto = 'supplier_order';

	/**
	 * @var int Multicompany management
	 */
	public $ismultientitymanaged = 1;

	/** @var int */
	public $rowid;
	/** @var int */
	public $id;
	/** @var int */
	public $entity;
	/** @var int|null */
	public $fk_user;
	/** @var int|null */
	public $fk_usergroup;
	/** @var string|null */
	public $amount_ht;
	/** @var int */
	public $unlimited = 0;
	/** @var int */
	public $active = 1;
	/** @var int|string|null */
	public $date_start;
	/** @var int|string|null */
	public $date_end;
	/** @var string|null */
	public $note_private;
	/** @var int|string|null */
	public $date_creation;
	/** @var int|string|null */
	public $tms;
	/** @var int */
	public $fk_user_creat;
	/** @var int|null */
	public $fk_user_modif;
	/** @var string|null */
	public $import_key;
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
	public $group_name;

	/**
	 * Field definitions.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => 1, 'position' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => 1, 'position' => 5),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'LmdbSupplierOrderLimitUser', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 10),
		'fk_usergroup' => array('type' => 'integer', 'label' => 'LmdbSupplierOrderLimitGroup', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 20),
		'amount_ht' => array('type' => 'price', 'label' => 'LmdbSupplierOrderLimitAmountHt', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 30),
		'unlimited' => array('type' => 'boolean', 'label' => 'LmdbSupplierOrderLimitUnlimited', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 40),
		'active' => array('type' => 'boolean', 'label' => 'LmdbSupplierOrderLimitActive', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 50),
		'date_start' => array('type' => 'datetime', 'label' => 'LmdbSupplierOrderLimitDateStart', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 60),
		'date_end' => array('type' => 'datetime', 'label' => 'LmdbSupplierOrderLimitDateEnd', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 70),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 80),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 500),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 501),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 510),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 511),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 1000),
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
	 * Validate object fields.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	public function validateFields()
	{
		global $langs;

		$this->errors = array();

		$this->fk_user = !empty($this->fk_user) ? (int) $this->fk_user : null;
		$this->fk_usergroup = !empty($this->fk_usergroup) ? (int) $this->fk_usergroup : null;
		$this->unlimited = empty($this->unlimited) ? 0 : 1;
		$this->active = empty($this->active) ? 0 : 1;

		if ((!empty($this->fk_user) && !empty($this->fk_usergroup)) || (empty($this->fk_user) && empty($this->fk_usergroup))) {
			$this->errors[] = $langs->trans('LmdbSupplierOrderLimitRuleInvalidTarget');
		}

		if (empty($this->unlimited)) {
			if ($this->amount_ht === null || $this->amount_ht === '') {
				$this->errors[] = $langs->trans('LmdbSupplierOrderLimitRuleAmountRequired');
			} else {
				$amount = function_exists('price2num') ? price2num($this->amount_ht, 'MU') : $this->amount_ht;
				if (!is_numeric($amount)) {
					$this->errors[] = $langs->trans('LmdbSupplierOrderLimitInvalidAmount');
				} elseif ((float) $amount < 0) {
					$this->errors[] = $langs->trans('LmdbSupplierOrderLimitRuleAmountNegative');
				} else {
					$this->amount_ht = (string) price2num($amount, 'MU');
				}
			}
		} else {
			$this->amount_ht = null;
		}

		if (!empty($this->errors)) {
			$this->error = implode("\n", $this->errors);
			return -1;
		}

		return 1;
	}

	/**
	 * Create a limit rule.
	 *
	 * @param User $user      User creating
	 * @param int  $notrigger 1 disables triggers
	 * @return int >0 if OK, -1 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = (int) $conf->entity;
		$this->fk_user_creat = (int) $user->id;

		if ($this->validateFields() < 0) {
			return -1;
		}

		$this->db->begin();

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, fk_user, fk_usergroup, amount_ht, unlimited, active, date_start, date_end, note_private, date_creation, fk_user_creat, import_key';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).', ';
		$sql .= ($this->fk_user ? ((int) $this->fk_user) : 'NULL').', ';
		$sql .= ($this->fk_usergroup ? ((int) $this->fk_usergroup) : 'NULL').', ';
		$sql .= ($this->amount_ht !== null && $this->amount_ht !== '' ? "'".$this->db->escape((string) $this->amount_ht)."'" : 'NULL').', ';
		$sql .= ((int) $this->unlimited).', ';
		$sql .= ((int) $this->active).', ';
		$sql .= $this->sqlDate($this->date_start).', ';
		$sql .= $this->sqlDate($this->date_end).', ';
		$sql .= ($this->note_private !== null ? "'".$this->db->escape((string) $this->note_private)."'" : 'NULL').', ';
		$sql .= "'".$this->db->idate(dol_now())."', ";
		$sql .= ((int) $this->fk_user_creat).', ';
		$sql .= ($this->import_key !== null ? "'".$this->db->escape((string) $this->import_key)."'" : 'NULL');
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;

		if (!$notrigger) {
			$result = $this->call_trigger('LMDBSUPPLIERORDERLIMIT_LIMIT_CREATE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Fetch a limit rule.
	 *
	 * @param int $id Row id
	 * @return int 1 if found, 0 if not found, -1 if KO
	 */
	public function fetch($id)
	{
		global $conf;

		$sql = 'SELECT t.rowid, t.entity, t.fk_user, t.fk_usergroup, t.amount_ht, t.unlimited, t.active,';
		$sql .= ' t.date_start, t.date_end, t.note_private, t.date_creation, t.tms, t.fk_user_creat, t.fk_user_modif, t.import_key';
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
	 * Update a limit rule.
	 *
	 * @param User $user      User updating
	 * @param int  $notrigger 1 disables triggers
	 * @return int 1 if OK, -1 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->id) && !empty($this->rowid)) {
			$this->id = (int) $this->rowid;
		}

		$oldcopy = new self($this->db);
		$oldresult = $oldcopy->fetch((int) $this->id);
		if ($oldresult <= 0) {
			$this->error = $oldresult < 0 ? $oldcopy->error : 'Record not found';
			return -1;
		}
		$this->oldcopy = $oldcopy;

		$this->entity = (int) $conf->entity;
		$this->fk_user_modif = (int) $user->id;

		if ($this->validateFields() < 0) {
			return -1;
		}

		$this->db->begin();

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' fk_user = '.($this->fk_user ? ((int) $this->fk_user) : 'NULL');
		$sql .= ', fk_usergroup = '.($this->fk_usergroup ? ((int) $this->fk_usergroup) : 'NULL');
		$sql .= ', amount_ht = '.($this->amount_ht !== null && $this->amount_ht !== '' ? "'".$this->db->escape((string) $this->amount_ht)."'" : 'NULL');
		$sql .= ', unlimited = '.((int) $this->unlimited);
		$sql .= ', active = '.((int) $this->active);
		$sql .= ', date_start = '.$this->sqlDate($this->date_start);
		$sql .= ', date_end = '.$this->sqlDate($this->date_end);
		$sql .= ', note_private = '.($this->note_private !== null ? "'".$this->db->escape((string) $this->note_private)."'" : 'NULL');
		$sql .= ', fk_user_modif = '.((int) $this->fk_user_modif);
		$sql .= ', import_key = '.($this->import_key !== null ? "'".$this->db->escape((string) $this->import_key)."'" : 'NULL');
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity = '.((int) $this->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		if (!$notrigger) {
			$this->context['trigger_reason'] = 'limit_update';
			$result = $this->call_trigger('LMDBSUPPLIERORDERLIMIT_LIMIT_UPDATE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Disable a limit rule.
	 *
	 * @param User $user      User disabling
	 * @param int  $notrigger 1 disables triggers
	 * @return int 1 if OK, -1 if KO
	 */
	public function disable($user, $notrigger = 0)
	{
		$this->active = 0;
		return $this->update($user, $notrigger);
	}

	/**
	 * Delete a limit rule.
	 *
	 * @param User $user      User deleting
	 * @param int  $notrigger 1 disables triggers
	 * @return int 1 if OK, -1 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->id) && !empty($this->rowid)) {
			$this->id = (int) $this->rowid;
		}

		$this->db->begin();

		if (!$notrigger) {
			$this->context['trigger_reason'] = 'limit_delete';
			$result = $this->call_trigger('LMDBSUPPLIERORDERLIMIT_LIMIT_DELETE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Fetch list.
	 *
	 * @param int                  $limit   Limit
	 * @param int                  $offset  Offset
	 * @param array<string, mixed> $filters Filters
	 * @return array<int, LmdbSupplierOrderLimitLimit>|int
	 */
	public function fetchAll($limit = 100, $offset = 0, $filters = array())
	{
		global $conf;

		$records = array();
		$sql = 'SELECT t.rowid, t.entity, t.fk_user, t.fk_usergroup, t.amount_ht, t.unlimited, t.active,';
		$sql .= ' t.date_start, t.date_end, t.note_private, t.date_creation, t.tms, t.fk_user_creat, t.fk_user_modif, t.import_key,';
		$sql .= ' u.login AS user_login, u.lastname AS user_lastname, u.firstname AS user_firstname, u.photo AS user_photo,';
		$sql .= ' u.statut AS user_status, u.email AS user_email, u.admin AS user_admin, u.entity AS user_entity, ug.nom AS group_name';
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS u ON u.rowid = t.fk_user';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'usergroup AS ug ON ug.rowid = t.fk_usergroup';
		$sql .= ' WHERE t.entity = '.((int) $conf->entity);
		$sql .= $this->buildWhereFromFilters($filters);
		$sql .= ' ORDER BY t.active DESC, t.fk_user IS NULL, t.fk_user, t.fk_usergroup';
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
	 * Return object URL.
	 *
	 * @param int    $withpicto Include picto
	 * @param string $option    Option
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		$url = dol_buildpath('/lmdbsupplierorderlimit/admin/limits.php', 1).'?id='.(int) $this->id;
		$label = '#'.(int) $this->id;
		return '<a href="'.$url.'">'.$label.'</a>';
	}

	/**
	 * Return status label.
	 *
	 * @param int $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut((int) $this->active, $mode);
	}

	/**
	 * Return status label.
	 *
	 * @param int $status Status
	 * @param int $mode   Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$label = $status ? $langs->trans('LmdbSupplierOrderLimitActive') : $langs->trans('LmdbSupplierOrderLimitInactive');
		$class = $status ? 'badge-status4' : 'badge-status5';

		if ($mode == 0) {
			return $label;
		}

		return '<span class="badge '.$class.'">'.$label.'</span>';
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
		$this->fk_user = $obj->fk_user !== null ? (int) $obj->fk_user : null;
		$this->fk_usergroup = $obj->fk_usergroup !== null ? (int) $obj->fk_usergroup : null;
		$this->amount_ht = $obj->amount_ht !== null ? (string) $obj->amount_ht : null;
		$this->unlimited = (int) $obj->unlimited;
		$this->active = (int) $obj->active;
		$this->date_start = $obj->date_start !== null ? $this->db->jdate($obj->date_start) : null;
		$this->date_end = $obj->date_end !== null ? $this->db->jdate($obj->date_end) : null;
		$this->note_private = $obj->note_private !== null ? (string) $obj->note_private : null;
		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->tms = $this->db->jdate($obj->tms);
		$this->fk_user_creat = (int) $obj->fk_user_creat;
		$this->fk_user_modif = $obj->fk_user_modif !== null ? (int) $obj->fk_user_modif : null;
		$this->import_key = $obj->import_key !== null ? (string) $obj->import_key : null;
		$this->user_login = isset($obj->user_login) && $obj->user_login !== null ? (string) $obj->user_login : null;
		$this->user_lastname = isset($obj->user_lastname) && $obj->user_lastname !== null ? (string) $obj->user_lastname : null;
		$this->user_firstname = isset($obj->user_firstname) && $obj->user_firstname !== null ? (string) $obj->user_firstname : null;
		$this->user_photo = isset($obj->user_photo) && $obj->user_photo !== null ? (string) $obj->user_photo : null;
		$this->user_status = isset($obj->user_status) && $obj->user_status !== null ? (int) $obj->user_status : null;
		$this->user_email = isset($obj->user_email) && $obj->user_email !== null ? (string) $obj->user_email : null;
		$this->user_admin = isset($obj->user_admin) && $obj->user_admin !== null ? (int) $obj->user_admin : null;
		$this->user_entity = isset($obj->user_entity) && $obj->user_entity !== null ? (int) $obj->user_entity : null;
		$this->group_name = isset($obj->group_name) && $obj->group_name !== null ? (string) $obj->group_name : null;
	}

	/**
	 * Convert a date value to SQL.
	 *
	 * @param int|string|null $date Date
	 * @return string
	 */
	private function sqlDate($date)
	{
		if (empty($date)) {
			return 'NULL';
		}

		if (is_numeric($date)) {
			return "'".$this->db->idate((int) $date)."'";
		}

		return "'".$this->db->escape((string) $date)."'";
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

		if (!empty($filters['fk_user'])) {
			$sql .= ' AND t.fk_user = '.((int) $filters['fk_user']);
		}
		if (!empty($filters['fk_usergroup'])) {
			$sql .= ' AND t.fk_usergroup = '.((int) $filters['fk_usergroup']);
		}
		if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null) {
			$sql .= ' AND t.active = '.((int) $filters['active']);
		}

		return $sql;
	}
}
