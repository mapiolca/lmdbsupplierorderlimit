<?php
// Test fixture only; native core is never modified.
class CommonObject
{
	public $db;
	public $error = '';
	public $errors = array();
	// Native field validation is outside this double; business scope checks remain real module code.
	public function validateField($fields, $name, $value) { return true; }
	public function getFieldError($name) { return ''; }
}
