<?php
class CommandeFournisseur
{
	public $db;
	public $id = 1;
	public $entity = 1;
	public $element = 'order_supplier';
	public $total_ht = '40';
	public $fk_project = 0;
	public $user_approve_id = 0;
	public $user_approve_id2 = 0;
	public $date_approve = null;
	public $date_approve2 = null;
	public function __construct($db) { $this->db = $db; }
	public function fetch($id) {
		if (!$this->db->native) { return 0; }
		$this->id = $id;
		$this->entity = (int) $this->db->native->entity;
		$this->total_ht = $this->db->native->total_ht;
		$this->fk_project = (int) $this->db->native->fk_projet;
		return 1;
	}
}
