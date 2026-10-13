<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
/** Native DoliDB transactions and DebugBar, with simulated SQL/persistence; no instance connection. */
$root = $argv[1] ?? '';
if (!is_file($root.'/debugbar/class/TraceableDB.php')) {
	fwrite(STDERR, "Usage: php test/native_activation.php /path/to/dolibarr/htdocs\n");
	exit(2);
}
define('DOL_DOCUMENT_ROOT', $root);
define('MAIN_DB_PREFIX', 'activation_test_');
require $root.'/core/db/mysqli.class.php';
require $root.'/debugbar/class/TraceableDB.php';
require __DIR__.'/../core/modules/modLmdbSupplierOrderLimit.class.php';
require __DIR__.'/../class/lmdbsupplierorderlimitconsumption.class.php';

function dol_syslog($message, $level = LOG_INFO, $indent = 0) { global $logs; $logs[] = $message; }
function getDolGlobalString($name, $default = '') { global $conf; return (string) ($conf->global->{$name} ?? $default); }
function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity) {
	global $conf;
	if ($entity !== 2) { throw new RuntimeException('Unexpected configuration entity'); }
	$conf->global->{$name} = $value;
	return 1;
}
function setEventMessages($message, $messages, $style) { global $notices; $notices[] = array($message, $style); }
function dol_include_once($path) { require_once dirname(__DIR__).substr($path, strlen('/lmdbsupplierorderlimit')); }
function ensure($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

/** Only SQL transport is simulated; begin/commit/rollback are inherited from native DoliDB. */
class ActivationTestDb extends DoliDBMysqli
{
	/** @var list<string> */
	public $statements = array();
	/** @var string */
	public $fail = '';
	public $historicalAmount = false;
	/** @var array{active:int,ready:int} */
	public $state = array('active' => 1, 'ready' => 0);
	/** @var array{active:int,ready:int} */
	private $snapshot = array('active' => 1, 'ready' => 0);
	public function __construct() { $this->transaction_opened = 0; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function fetch_object($resultset) { return array_shift($resultset->rows) ?: false; }
	public function lasterror() { return 'Simulated SQL failure'; }
	public function lasterrno() { return 'TEST'; }
	public function query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		$this->statements[] = $query;
		if ($this->fail === 'private_exception') { throw new RuntimeException('private customer SQL token=secret'); }
		if ($this->fail !== '' && strpos($query, $this->fail) !== false) { return false; }
		$rows = array();
		if ($query === 'BEGIN') { $this->snapshot = $this->state; }
		elseif ($query === 'ROLLBACK') { $this->state = $this->snapshot; }
		elseif ($query === 'COMMIT') {}
		elseif (strpos($query, 'SHOW COLUMNS FROM activation_test_lmdbsupplierorderlimit_consumption') === 0) { $rows[] = (object) array('Field'=>'snapshot_amount_ht', 'Null'=>'YES'); }
		elseif (strpos($query, 'SHOW COLUMNS') === 0) { $rows[] = (object) array('Field' => 'limit_type'); }
		elseif (strpos($query, 'SHOW INDEX') === 0) {
			$rows = array((object) array('Key_name' => 'uk_lmdbsol_user_type'), (object) array('Key_name' => 'uk_lmdbsol_group_type'));
		}
		elseif (strpos($query, 'SELECT rowid FROM activation_test_lmdbsupplierorderlimit_lock') === 0) { $rows[] = (object) array('rowid' => 1); }
		elseif (strpos($query, 'SELECT rowid,entity,fk_projet') === 0) {
			if ($this->historicalAmount) { $rows[] = (object) array('rowid'=>1, 'entity'=>2, 'fk_projet'=>0, 'total_ht'=>null, 'fk_user_approve'=>null, 'fk_user_approve2'=>null, 'date_approve'=>null, 'date_approve2'=>null); }
		}
		elseif (strpos($query, 'SELECT * FROM activation_test_lmdbsupplierorderlimit_consumption') === 0) {}
		elseif (strpos($query, 'INSERT INTO activation_test_lmdbsupplierorderlimit_consumption') === 0) {}
		elseif (strpos($query, 'UPDATE activation_test_lmdbsupplierorderlimit_consumption SET active = 0 WHERE entity = 2') === 0) { $this->state['active'] = 0; }
		elseif (strpos($query, 'UPDATE activation_test_lmdbsupplierorderlimit_lock SET reconciled = 1 WHERE entity = 2') === 0) { $this->state['ready'] = 1; }
		elseif (strpos($query, 'INSERT IGNORE INTO activation_test_lmdbsupplierorderlimit_lock') !== 0) { throw new RuntimeException('Unexpected test query'); }
		return (object) array('rows' => $rows);
	}
}

/** DDL loading and native module registration are excluded; the module's init() runs unchanged. */
class ActivationTestModule extends modLmdbSupplierOrderLimit
{
	/** @var bool */
	public $registered = false;
	protected function _load_tables($reldir, $onlywithsuffix = '') { return 1; }
	protected function _init($array_sql, $options = '') { $this->registered = true; return 1; }
}
class ActivationTestLangs
{
	public function load($catalog) {}
	public function trans($key) { return $key; }
}

$langs = new ActivationTestLangs();
foreach (array('', 'historical_amount', 'BEGIN', 'SET reconciled = 1', 'COMMIT', 'SHOW COLUMNS', 'private_exception') as $failure) {
	foreach (array(0, 1, 2) as $wrappers) {
		$logs = array();
		$notices = array();
		$conf = (object) array('entity' => 2, 'global' => (object) array(
			'LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS' => '0',
			'LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE' => '',
			'MULTICOMPANY_EXTERNAL_MODULES_SHARING' => '{"other_module":{"keep":true}}',
		));
		$driver = new ActivationTestDb();
		$db = $driver;
		for ($i = 0; $i < $wrappers; $i++) { $db = new TraceableDB($db); }
		$ledger = new LmdbSupplierOrderLimitConsumption($db);
		try { $ledger->lock(2); throw new LogicException('Missing transaction refusal'); }
		catch (RuntimeException $e) { ensure($e->getMessage() === 'transaction_required', 'Wrong refusal'); }
		ensure(!$driver->statements, 'Out-of-transaction lock issued SQL');
		$driver->historicalAmount = $failure === 'historical_amount';
		$driver->fail = $failure === 'historical_amount' ? '' : $failure;
		$module = new ActivationTestModule($db);
		$result = $module->init();
		if ($failure === '' || $failure === 'historical_amount') {
			ensure($result === 1 && $module->registered, 'Activation failed with '.$wrappers.' DebugBar wrapper(s)');
			ensure($driver->state === array('active' => 0, 'ready' => 1), 'Reconciliation not committed');
			ensure($module->init() === 1, 'Reactivation failed');
			if ($failure === 'historical_amount') {
				ensure(in_array(array('LimitHistoryIncomplete', 'warnings'), $notices, true), 'Missing historical warning');
				ensure((bool) preg_grep('/VALUES \(2,1,NULL,NULL,NULL,NULL,1,1\)/', $driver->statements), 'Unknown amount not preserved');
			}
			ensure(getDolGlobalString('LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS') === '0' && getDolGlobalString('LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE') === '', 'Existing settings lost');
			$sharing = json_decode(getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING'), true);
			ensure($sharing['other_module']['keep'] === true, 'Other module sharing lost');
			if ($wrappers > 0) { ensure((bool) preg_grep('/scope_key.*FOR UPDATE$/', array_column($db->queries, 'sql')), 'Lock queries bypassed DebugBar'); }
			// The ledger joins a native caller's transaction without changing its nesting level.
			$db->begin();
			$db->begin();
			$ledger->lock(2, array(8, 3));
			ensure($driver->transaction_opened === 2, 'Ledger changed caller transaction depth');
			$db->rollback();
			$db->rollback();
		} else {
			ensure($result === -1 && !$module->registered, 'Failed transaction activated module');
			ensure($driver->state === array('active' => 1, 'ready' => 0), 'Failed reconciliation left partial writes');
			$needsRollback = in_array($failure, array('SET reconciled = 1', 'COMMIT'), true);
			ensure(in_array('ROLLBACK', $driver->statements, true) === $needsRollback, 'Incorrect rollback ownership');
			ensure((bool) preg_grep('/modLmdbSupplierOrderLimit::init.*failed/', $logs), 'Missing activation diagnostic');
			$expectedReasons = array('BEGIN'=>'transaction_start_failed', 'SET reconciled = 1'=>'technical_error',
				'COMMIT'=>'transaction_commit_failed', 'SHOW COLUMNS'=>'LimitTechnicalError', 'private_exception'=>'unexpected_error');
			ensure((bool) preg_grep('/reason='.$expectedReasons[$failure].' /', $logs), 'Missing safe failure reason');
			ensure(strpos(implode("\n", $logs), 'private customer') === false && strpos(implode("\n", $logs), 'token=') === false, 'Private exception leaked');
		}
		ensure($driver->transaction_opened === 0, 'Transaction leaked');
		echo 'OK wrappers='.$wrappers.' failure='.($failure ?: 'none')."\n";
	}
}
echo 'Native activation/transaction checks passed (PHP '.PHP_VERSION."); SQL and registration simulated.\n";
