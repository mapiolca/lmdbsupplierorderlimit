<?php
class DaoMulticompany
{
	public static $configs = array();
	public static $shares = array();
	public $active = 1;
	public $options = array();
	public $label = '';
	public function __construct($db) {}
	public function fetch($id) { $this->label = 'Entity '.$id; $this->options = array('sharings'=>array('lmdbsupplierorderlimit_limit'=>self::$shares[$id] ?? array())); return $id > 0 ? 1 : -1; }
	public function getEntityConfig($id, $name = null) { return self::$configs[$id] ?? array(); }
}
