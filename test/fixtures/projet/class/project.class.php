<?php
class Project
{
	public $entity = 1;
	public static $accessible = true;
	public function __construct($db) {}
	public function fetch($id) { return $id > 0 ? 1 : 0; }
	public function restrictedProjectArea($user, $mode) { return self::$accessible ? 1 : -1; }
}
