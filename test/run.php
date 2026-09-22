<?php
/** Isolated business/SQL-contract simulations. No connection, instance or external effects. */
define('DOL_DOCUMENT_ROOT', __DIR__.'/fixtures');
define('MAIN_DB_PREFIX', 'test_prefix_');
date_default_timezone_set('Europe/Paris');
require DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require __DIR__.'/../class/lmdbsupplierorderlimitauthorizer.class.php';
require __DIR__.'/../core/triggers/interface_99_modLmdbSupplierOrderLimit_LmdbSupplierOrderLimitTriggers.class.php';
require __DIR__.'/../class/lmdbsupplierorderlimitmigration.class.php';
require __DIR__.'/../class/lmdbsupplierorderlimitlimit.class.php';

function price2num($value, $mode = '') { return $mode ? round((float) $value, 2) : (string) $value; }
function price($value) { return (string) $value; }
function getDolGlobalString($key, $default = '') { global $conf; return (string) ($conf->global->{$key} ?? $default); }
function getDolGlobalInt($key, $default = 0) { return (int) getDolGlobalString($key, $default); }
function getEntity($name) { global $entities; return $entities[$name] ?? '1'; }
function dol_now() { return strtotime('2026-09-22 12:00:00'); }
function isModEnabled($name) { global $multicompany; return $name === 'lmdbsupplierorderlimit' || ($name === 'multicompany' && $multicompany); }
function restrictedArea(...$args) { global $access; return $access ? 1 : 0; }
function dol_syslog($message, $level) {}
function dol_include_once($path) {
	if (strpos($path,'/multicompany/') === 0) { require_once DOL_DOCUMENT_ROOT.$path; }
	else { require_once dirname(__DIR__).substr($path, strlen('/lmdbsupplierorderlimit')); }
}

class TestUser
{
	public $id = 10;
	public $admin = 0;
	public $denied = array('fournisseur.commande.approve2');
	public function hasRight(...$parts) { return !in_array(implode('.', $parts), $this->denied, true); }
}
class TestResult { public $rows; public function __construct($rows) { $this->rows = $rows; } }
class TestDb
{
	public $transaction_opened = 1;
	public $queries = array();
	public $rules = array();
	public $spent = array();
	public $projectSpent = array();
	public $budget = '100';
	public $ready = 1;
	public $unresolved = false;
	public $entry = null;
	public $native = null;
	public $targetAccessible = true;
	public $fail = '';
	public $columns = array();
	public $indexes = array('uk_lmdbsupplierorderlimit_limit_user_entity','uk_lmdbsupplierorderlimit_limit_group_entity');
	public function escape($s) { return str_replace("'", "''", $s); }
	public function sanitize($s) { return $s; }
	public function idate($time) { return date('Y-m-d H:i:s', $time); }
	public function jdate($date) { return strtotime($date); }
	public function fetch_object($result) { return array_shift($result->rows) ?: false; }
	public function query($sql) {
		$this->queries[] = $sql;
		if ($this->fail && strpos($sql, $this->fail) !== false) { return false; }
		$rows = array();
		if (strpos($sql, 'SELECT t.rowid,t.entity') === 0) { $rows = $this->rules; }
		elseif (strpos($sql, 'SELECT reconciled') === 0) { $rows = array((object) array('reconciled' => $this->ready)); }
		elseif (strpos($sql, 'SELECT rowid FROM test_prefix_lmdbsupplierorderlimit_consumption') === 0) { $rows = $this->unresolved ? array((object) array('rowid' => 99)) : array(); }
		elseif (strpos($sql, 'SELECT snapshot_amount_ht') === 0) { $rows = array_map(static function ($v) { return (object) array('snapshot_amount_ht' => $v); }, $this->spent); }
		elseif (strpos($sql, 'SELECT c.total_ht') === 0) { $rows = array_map(static function ($v) { return (object) array('total_ht' => $v,'entity'=>1); }, $this->projectSpent); }
		elseif (strpos($sql, 'SELECT budget_amount') === 0) { $rows = array((object) array('budget_amount' => $this->budget)); }
		elseif (strpos($sql, 'SELECT u.rowid FROM test_prefix_user u') === 0 || strpos($sql, 'SELECT rowid FROM test_prefix_usergroup WHERE') === 0) { $rows = $this->targetAccessible ? array((object) array('rowid'=>10)) : array(); }
		elseif (strpos($sql, 'SELECT * FROM test_prefix_lmdbsupplierorderlimit_consumption') === 0) { $rows = $this->entry ? array($this->entry) : array(); }
		elseif (strpos($sql, 'SELECT rowid, entity, fk_soc') === 0) { $rows = $this->native ? array($this->native) : array(); }
		elseif (strpos($sql, 'SELECT rowid,entity,fk_projet') === 0) { $rows = $this->native ? array($this->native) : array(); }
		elseif (strpos($sql, 'SELECT rowid FROM test_prefix_lmdbsupplierorderlimit_lock') === 0) { $rows = array((object) array('rowid' => 1)); }
		elseif (strpos($sql, 'SHOW COLUMNS') === 0) { $rows = array_map(static function ($v) { return (object) array('Field' => $v); }, $this->columns); }
		elseif (strpos($sql, 'SHOW INDEX') === 0) { $rows = array_map(static function ($v) { return (object) array('Key_name' => $v); }, $this->indexes); }
		elseif (preg_match('/ ADD limit_type /', $sql)) { $this->columns[] = 'limit_type'; }
		elseif (preg_match('/ ADD UNIQUE KEY ([a-z_]+)/', $sql, $m)) { $this->indexes[] = $m[1]; }
		elseif (preg_match('/ DROP INDEX ([a-z_]+)/', $sql, $m)) { $this->indexes = array_values(array_diff($this->indexes, array($m[1]))); }
		elseif (!preg_match('/^(INSERT IGNORE|INSERT INTO|UPDATE)/', $sql)) { throw new RuntimeException('Unexpected SQL: '.$sql); }
		return new TestResult($rows);
	}
}
class TestLangs { public function load($s) {} public function trans($s, ...$args) { return $s.implode(' ', $args); } }
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function rule($id, $type, $amount, $uid = 10, $group = 0, $entity = 1, $unlimited = 0) {
	return array('rowid' => $id, 'entity' => $entity, 'fk_user' => $uid, 'fk_usergroup' => $group, 'limit_type' => $type, 'amount_ht' => $amount, 'unlimited' => $unlimited);
}
function resetCase() {
	global $conf, $entities, $access, $langs, $multicompany;
	$multicompany = false;
	$conf = (object) array('entity' => 1, 'currency' => 'EUR', 'global' => new stdClass());
	$entities = array('lmdbsupplierorderlimit_limit' => '1,2,3');
	$access = true;
	$langs = new TestLangs();
	Project::$accessible = true;
}
function decision($rules, $amount = '40', $spent = array(), $locked = false) {
	$db = new TestDb(); $db->rules = array_map(static function ($r) { return (object) $r; }, $rules); $db->spent = $spent;
	$order = new CommandeFournisseur($db); $order->total_ht = $amount;
	return array(LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db, new TestUser(), $order, 1, $locked, true), $db);
}
$tests = array();
$tests['personal priority per nature and local before shared'] = static function () {
	$rules = array(rule(1,'order','20'),rule(2,'order','500',0,4),rule(3,'day','300',0,4),rule(4,'order','900',10,0,2));
	$selected = LmdbSupplierOrderLimitPolicy::select($rules, 1);
	check($selected['order']['rowid'] === 1 && $selected['day']['rowid'] === 3, 'priority');
	$selected = LmdbSupplierOrderLimitPolicy::select(array(rule(1,'order','20',0,4),rule(2,'order','900',0,4,2),rule(3,'order','50',0,5)),1);
	check($selected['order']['rowid'] === 3, 'local group override, highest group');
	$selected = LmdbSupplierOrderLimitPolicy::select(array(rule(1,'order','20',10,0,2),rule(2,'order','30',10,0,3)),1);
	check($selected['order']['rowid'] === 2, 'highest shared');
};
$tests['unlimited neutralizes only its nature and zero is enforced'] = static function () {
	list($d) = decision(array(rule(1,'order',null,10,0,1,1),rule(2,'order','1',0,4),rule(3,'day','0')), '1');
	check(!$d['allowed'] && $d['checks'][0]['reason'] === 'unlimited' && !$d['checks'][1]['allowed'], 'unlimited nature');
	list($d) = decision(array(rule(1,'order','0')), '0'); check($d['allowed'], 'zero equality');
};
$tests['orders are independent; consultation performs no write'] = static function () {
	list($d,$db) = decision(array(rule(1,'order','100')), '101'); check(!$d['allowed'], 'over');
	list($d,$db) = decision(array(rule(1,'order','100')), '100'); check($d['allowed'], 'equality after failure');
	check(count($db->queries) === 1 && strpos($db->queries[0], 'SELECT') === 0, 'consultation must not consume');
};
$tests['periodic equality, combined checks and personal entity attribution'] = static function () {
	$rules = array(rule(1,'order','50'),rule(2,'day','100'),rule(3,'month','200'),rule(4,'year','300'));
	list($d,$db) = decision($rules,'40',array('60'),true); check($d['allowed'], 'equality');
	list($d) = decision($rules,'40.01',array('60')); check(!$d['allowed'], 'overflow');
	$sql = implode("\n", $db->queries);
	check(strpos($sql,'entity = 1 AND fk_user = 10') !== false && strpos($sql,'fk_supplier_order <> 1') !== false, 'scope and exclusion');
	check(strpos($sql,'LOCK IN SHARE MODE') !== false, 'current read');
	check(strpos($sql,'gu.entity IN (0,1)') !== false && strpos($sql,'g.entity IN (0,1)') !== false, 'membership context');
	check(strpos($sql,'t.active = 1') !== false && strpos($sql,'t.date_end >=') !== false, 'inactive/expired fallback SQL');
};
$tests['no-rule policy and technical errors fail closed'] = static function () {
	global $conf;
	list($d) = decision(array()); check($d['allowed'], 'default unlimited');
	$conf->global->LMDBSUPPLIERORDERLIMIT_DEFAULT_NO_LIMIT_BEHAVIOR = 'deny';
	list($d) = decision(array()); check(!$d['allowed'], 'deny');
	$db = new TestDb(); $db->fail = 'SELECT t.rowid';
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),new CommandeFournisseur($db),1,false,true);
	check(!$d['allowed'] && $d['reason'] === 'technical_error','SQL must not mean no rule');
};
$tests['administrator without native permission and inaccessible order refused'] = static function () {
	global $access;
	$db = new TestDb(); $u = new TestUser(); $u->admin = 1; $u->denied[] = 'fournisseur.commande.approuver';
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,new CommandeFournisseur($db),1,false,true);
	check(!$d['allowed'], 'no admin exemption');
	$access = false;
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),new CommandeFournisseur($db),1,false,true); check(!$d['allowed'], 'parent access');
};
$tests['project: absent, null, zero, inaccessible and private aggregate'] = static function () {
	$db = new TestDb(); $db->rules = array((object) rule(1,'project_budget',null)); $order = new CommandeFournisseur($db); $u = new TestUser();
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,$order,1,false,true); check($d['allowed'], 'no project');
	$order->fk_project = 8; $db->budget = null;
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,$order,1,false,true); check($d['allowed'], 'null budget');
	$db->budget = '0';
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,$order,1,false,true); check(!$d['allowed'], 'zero budget');
	$db->budget = '100'; $db->projectSpent = array('60');
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,$order,1,false,true); check($d['allowed'], 'project equality');
	check($d['checks'][0]['consumed'] === null && $d['checks'][0]['projected'] === null, 'private aggregate');
	Project::$accessible = false;
	$d = LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,$u,$order,1,false,true); check(!$d['allowed'] && $d['reason'] === 'project_inaccessible', 'no inaccessible waiver');
};
$tests['civil DST, leap year and rolling elapsed windows'] = static function () {
	foreach (array('2026-03-29 12:00:00' => 23, '2026-10-25 12:00:00' => 25) as $date => $hours) {
		$p = LmdbSupplierOrderLimitPolicy::period('day','civil',strtotime($date),'Europe/Paris'); check($p['end']-$p['start'] === $hours*3600,'DST civil');
		$p = LmdbSupplierOrderLimitPolicy::period('day','rolling',strtotime($date),'Europe/Paris'); check($p['end']-$p['start'] === 86400,'rolling 24h');
	}
	$p = LmdbSupplierOrderLimitPolicy::period('month','civil',strtotime('2024-02-29 12:00:00'),'Europe/Paris'); check(date('Y-m-d',$p['start']) === '2024-02-01' && date('Y-m-d',$p['end']) === '2024-03-01','leap month');
	$p = LmdbSupplierOrderLimitPolicy::period('year','civil',strtotime('2024-12-31 12:00:00'),'Europe/Paris'); check(date('Y-m-d',$p['end']) === '2025-01-01','year rollover');
	foreach (array('month'=>30,'year'=>365) as $type=>$days) { $p=LmdbSupplierOrderLimitPolicy::period($type,'rolling',dol_now(),'Europe/Paris'); check($p['end']-$p['start'] === $days*86400,'rolling period'); }
};
$tests['history ambiguity blocks cumulative controls; order unaffected'] = static function () {
	$db = new TestDb(); $db->unresolved = true; $db->rules = array((object) rule(1,'day','100'));
	$d=LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),new CommandeFournisseur($db),1,false,true); check(!$d['allowed'] && $d['reason']==='history_incomplete','history block');
	$db->rules = array((object) rule(1,'order','100'));
	$d=LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),new CommandeFournisseur($db),1,false,true); check($d['allowed'],'order independent');
	$ledger=new LmdbSupplierOrderLimitConsumption($db);
	$row=(object) array('fk_user_approve'=>10,'fk_user_approve2'=>11,'date_approve'=>'2026-09-22 12:00:00','date_approve2'=>'2026-09-22 12:00:00');
	check($ledger->attribution($row)['user']===null,'same-second ambiguity');
	$row->date_approve2='2026-09-22 12:00:01'; check($ledger->attribution($row)['user']===11,'known final actor');
};
$tests['locks stable, transaction mandatory, lock failure refuses'] = static function () {
	$db=new TestDb(); $ledger=new LmdbSupplierOrderLimitConsumption($db); $ledger->lock(1,array(9,2,9));
	$locked=array_values(array_filter($db->queries,static function($sql){return strpos($sql,'FOR UPDATE')!==false;}));
	check(count($locked)===3 && strpos($locked[0],'entity:1')!==false && strpos($locked[1],'project:2')!==false,'lock order');
	$db->transaction_opened=0; try { $ledger->lock(1); throw new LogicException('missing refusal'); } catch (RuntimeException $e) { check($e->getMessage()==='transaction_required','transaction'); }
	$db->transaction_opened=1; $db->fail='FOR UPDATE'; try { $ledger->lock(1); throw new LogicException('missing refusal'); } catch (RuntimeException $e) { check($e->getMessage()==='technical_error','lock error'); }
};
$tests['native lifecycle: final, replay, cancel, reapproval and amendment'] = static function () {
	global $conf,$langs;
	$db=new TestDb(); $db->native=(object) array('rowid'=>1,'entity'=>1,'fk_soc'=>5,'fk_projet'=>0,'fk_statut'=>2,'total_ht'=>'40','fk_user_approve'=>10,'fk_user_approve2'=>null,'date_approve'=>'2026-09-22 12:00:00','date_approve2'=>null);
	$trigger=new InterfaceLmdbSupplierOrderLimitTriggers($db); $before=new CommandeFournisseur($db); $u=new TestUser();
	check($trigger->runTrigger('ORDER_SUPPLIER_APPROVE',$before,$u,$langs,$conf)===0,'final approval');
	check(count(array_filter($db->queries,static function($s){return strpos($s,'INSERT INTO test_prefix_lmdbsupplierorderlimit_consumption')===0;}))===1,'one consumption');
	$db->entry=(object) array('entity'=>1,'fk_project'=>0,'active'=>1,'snapshot_amount_ht'=>'40','fk_user'=>10,'date_approval'=>'2026-09-22 12:00:00','unresolved'=>0); $db->queries=array();
	check($trigger->runTrigger('ORDER_SUPPLIER_APPROVE',$before,$u,$langs,$conf)===0,'replay');
	check(!preg_grep('/^INSERT INTO /',$db->queries),'no repeated consumption');
	$db->native->fk_statut=6; $db->queries=array(); check($trigger->runTrigger('ORDER_SUPPLIER_CANCEL',$before,$u,$langs,$conf)===0,'cancel'); check((bool) preg_grep('/SET active = 0/',$db->queries),'release');
	$db->entry->active=0; $db->native->fk_statut=2; check($trigger->runTrigger('ORDER_SUPPLIER_APPROVE',$before,$u,$langs,$conf)===0,'reapprove');
	$db->entry->active=1; $db->native->total_ht='80'; $db->rules=array((object) rule(1,'order','50'));
	check($trigger->runTrigger('ORDER_SUPPLIER_MODIFY',$before,$u,$langs,$conf)===-1,'over-budget amendment');
};
$tests['double approval consumes only final approval and preserves actor'] = static function () {
	global $conf,$langs;
	$db=new TestDb(); $db->native=(object) array('rowid'=>1,'entity'=>1,'fk_soc'=>5,'fk_projet'=>0,'fk_statut'=>1,'total_ht'=>'40','fk_user_approve'=>10,'fk_user_approve2'=>null,'date_approve'=>'2026-09-22 12:00:00','date_approve2'=>null);
	$trigger=new InterfaceLmdbSupplierOrderLimitTriggers($db); $before=new CommandeFournisseur($db); $u=new TestUser(); $u->denied=array();
	check($trigger->runTrigger('ORDER_SUPPLIER_APPROVE',$before,$u,$langs,$conf)===0,'provisional'); check(!preg_grep('/^INSERT INTO /',$db->queries),'no provisional consumption');
	$before->user_approve_id=10; $before->date_approve=dol_now(); $db->native->fk_statut=2; $db->native->fk_user_approve2=20; $db->native->date_approve2='2026-09-22 12:00:01'; $u->id=20;
	check($trigger->runTrigger('ORDER_SUPPLIER_APPROVE',$before,$u,$langs,$conf)===0,'second final'); check((bool) preg_grep('/VALUES \(1,1,20,/',$db->queries),'final actor');
};
$tests['migration repeatable and old identifiers preserved'] = static function () {
	$db=new TestDb(); LmdbSupplierOrderLimitMigration::schema($db); $count=count($db->queries); LmdbSupplierOrderLimitMigration::schema($db);
	check(count($db->queries)===$count+2,'idempotent DDL'); check(in_array('uk_lmdbsol_user_type',$db->indexes,true),'new uniqueness');
	check(!preg_grep('/^(DELETE|UPDATE) /',$db->queries),'no rewriting existing rules');
};
$tests['owner configuration and transverse/global group scope without switching session'] = static function () {
	global $multicompany,$conf;
	$multicompany=true;
	dol_include_once('/multicompany/class/dao_multicompany.class.php');
	DaoMulticompany::$configs=array(0=>array('MULTICOMPANY_TRANSVERSE_MODE'=>'1'),2=>array('MAIN_MODULE_LMDBSUPPLIERORDERLIMIT'=>'1','LMDBSUPPLIERORDERLIMIT_DAY_MODE'=>'rolling','MULTICOMPANY_SHARINGS_ENABLED'=>'1','MULTICOMPANY_LMDBSUPPLIERORDERLIMIT_LIMIT_SHARING_ENABLED'=>'1'));
	DaoMulticompany::$shares=array(2=>array(3));
	$scope=LmdbSupplierOrderLimitScope::context(new TestDb(),2);
	check($scope['entities']===array(2,3) && $scope['users']===array(0,1) && $scope['groups']===array(0,2),'owner sharing and transverse targets');
	check($scope['settings']['LMDBSUPPLIERORDERLIMIT_DAY_MODE']==='rolling' && $conf->entity===1,'owner setting without session mutation');
	DaoMulticompany::$configs[2]=-1;
	try { LmdbSupplierOrderLimitScope::context(new TestDb(),2); throw new LogicException('missing error'); } catch(RuntimeException $e) { check($e->getMessage()==='technical_error','owner read failure'); }
};
$tests['three sharing hooks expose one definition and workflow restores state'] = static function () {
	global $conf;
	$hooks=new ActionsLmdbSupplierOrderLimit(new TestDb()); $object=null; $action='';
	foreach(array('multicompanyExternalModulesSharing','multicompanyExternalModuleSharing','multicompanySharingOptions') as $method) {
		$hooks->results=array(); $hooks->{$method}(array(),$object,$action,null);
		check($hooks->results===ActionsLmdbSupplierOrderLimit::getMulticompanySharingDefinition(),'one definition');
	}
	$scope=new ReflectionMethod($hooks,'scopeWorkflow'); $scope->setAccessible(true); $order=new CommandeFournisseur(new TestDb());
	$conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE=0;
	$scope->invoke($hooks,$order,'SUPPLIER_ORDER_NO_DIRECT_APPROVE',1);
	$scope->invoke($hooks,$order,'SUPPLIER_ORDER_3_STEPS_TO_BE_APPROVED','40');
	ActionsLmdbSupplierOrderLimit::restoreWorkflow();
	check($conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE===0 && !property_exists($conf->global,'SUPPLIER_ORDER_3_STEPS_TO_BE_APPROVED'),'restore absent and zero');
	$scope->invoke($hooks,$order,'SUPPLIER_ORDER_NO_DIRECT_APPROVE',1);
	$conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE=2;
	ActionsLmdbSupplierOrderLimit::restoreWorkflow(); check($conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE===2,'preserve intervening change');
};
$tests['different shared base currencies are refused'] = static function () {
	global $multicompany;
	$multicompany=true;
	dol_include_once('/multicompany/class/dao_multicompany.class.php');
	DaoMulticompany::$configs=array(0=>array(),2=>array('MAIN_MONNAIE'=>'USD'));
	try { LmdbSupplierOrderLimitScope::assertCommonCurrency(new TestDb(),array(1,2)); throw new LogicException('missing refusal'); }
	catch(RuntimeException $e) { check($e->getMessage()==='currency_mismatch','no implicit conversion'); }
	DaoMulticompany::$configs[2]['MAIN_MONNAIE']='EUR';
	LmdbSupplierOrderLimitScope::assertCommonCurrency(new TestDb(),array(1,2));
};
$tests['all five natures must pass together'] = static function () {
	$db=new TestDb(); $order=new CommandeFournisseur($db); $order->fk_project=8;
	$db->rules=array_map(static function($r){return (object) $r;}, array(rule(1,'order','40'),rule(2,'day','100'),rule(3,'month','100'),rule(4,'year','100'),rule(5,'project_budget',null)));
	$db->spent=array('60'); $db->projectSpent=array('60');
	$d=LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),$order,1,false,true);
	check($d['allowed'] && count($d['checks'])===5,'five equalities');
	$db->budget='99';
	$d=LmdbSupplierOrderLimitAuthorizer::canApproveSupplierOrder($db,new TestUser(),$order,1,false,true);
	check(!$d['allowed'] && !$d['checks'][4]['allowed'],'project alone denies');
};
$tests['reconciliation retains ambiguity and releases inactive history without native writes'] = static function () {
	$db=new TestDb(); $ledger=new LmdbSupplierOrderLimitConsumption($db);
	$db->native=(object) array('rowid'=>1,'entity'=>1,'fk_projet'=>8,'total_ht'=>'40','fk_user_approve'=>10,'fk_user_approve2'=>11,'date_approve'=>'2026-09-22 12:00:00','date_approve2'=>'2026-09-22 12:00:00');
	check($ledger->reconcile(1)===1,'ambiguous history stays unresolved');
	check((bool) preg_grep('/VALUES \(1,1,NULL,8,NULL,/',$db->queries),'no invented actor/date');
	$db->entry=(object) array('fk_user'=>11,'date_approval'=>'2026-09-22 12:00:00','unresolved'=>0);
	check($ledger->reconcile(1)===0,'observed final actor resolves same second');
	$db->native=null; $db->queries=array();
	check($ledger->reconcile(1)===0,'reconciliation with no approved orders');
	check((bool) preg_grep('/SET active = 0 WHERE entity = 1$/',$db->queries),'old consumption released');
	check(!preg_grep('/^(INSERT|UPDATE|DELETE).*test_prefix_commande_fournisseur/',$db->queries),'native data never rewritten');
};
$tests['non-accounting native events do not acquire locks or consume'] = static function () {
	global $langs,$conf;
	$db=new TestDb(); $db->transaction_opened=0;
	$trigger=new InterfaceLmdbSupplierOrderLimitTriggers($db); $order=new CommandeFournisseur($db);
	foreach(array('ORDER_SUPPLIER_SENTBYMAIL','ORDER_SUPPLIER_BUILDDOC','ORDER_SUPPLIER_CLASSIFY_BILLED') as $event) {
		check($trigger->runTrigger($event,$order,new TestUser(),$langs,$conf)===0,'unrelated event');
	}
	check(!$db->queries,'no accounting side effect');
	$db->transaction_opened=1;
	$db->native=(object) array('rowid'=>1,'entity'=>1,'fk_soc'=>5,'fk_projet'=>0,'fk_statut'=>2,'total_ht'=>'40','fk_user_approve'=>null,'fk_user_approve2'=>null,'date_approve'=>null,'date_approve2'=>null);
	check($trigger->runTrigger('ORDER_SUPPLIER_STATUS_APPROVED',$order,new TestUser(),$langs,$conf)===-1,'setStatus cannot bypass native approval levels');
};
$tests['rule writes reject inaccessible beneficiaries and administrators without write permission'] = static function () {
	global $conf;
	$db=new TestDb(); $rule=new LmdbSupplierOrderLimitLimit($db); $rule->entity=1; $rule->fk_user=10; $rule->amount_ht='50';
	check($rule->validateFields()===1,'valid business target');
	$db->targetAccessible=false;
	check($rule->validateFields()===-1,'inaccessible user refused');
	$rule->fk_user=null; $rule->fk_usergroup=4;
	check($rule->validateFields()===-1,'inaccessible group refused');
	$db->targetAccessible=true; $rule->fk_user=10; $rule->fk_usergroup=null;
	$conf->global->MULTICOMPANY_TRANSVERSE_MODE=1;
	$rule->validateFields();
	check(strpos(end($db->queries),'gu.entity IN (0,1)')!==false,'transverse membership checked server-side');
	$user=new TestUser(); $user->admin=1; $user->denied[]='lmdbsupplierorderlimit.limit.write';
	check($rule->create($user)===-1,'no administrator elevation on rule writes');
};
$passed=0;
foreach ($tests as $name=>$test) {
	resetCase();
	try { $test(); $passed++; echo "OK $name\n"; } catch (Throwable $e) { fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1); }
}
echo "$passed simulated scenarios passed; no instance or concurrent InnoDB test performed.\n";
