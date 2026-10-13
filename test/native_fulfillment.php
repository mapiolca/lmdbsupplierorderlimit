<?php
/** Execute unmodified native setStatus()/Livraison() bodies with simulated SQL and transactions.
 * No Dolibarr bootstrap, database connection, stock movement or external business effect.
 */
$sourcePath = $argv[1] ?? '';
if (!is_file($sourcePath)) { fwrite(STDERR, "Usage: php test/native_fulfillment.php /path/to/fournisseur.commande.class.php\n"); exit(2); }
require __DIR__.'/run.php';

// Token extraction keeps braces inside strings/comments from changing method boundaries.
$tokens = token_get_all(file_get_contents($sourcePath));
$methods = array();
for ($i = 0; $i < count($tokens); $i++) {
	if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
	$j = $i + 1;
	while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
	if (!isset($tokens[$j]) || !is_array($tokens[$j]) || !in_array($tokens[$j][1], array('setStatus', 'Livraison'), true)) { continue; }
	$name = $tokens[$j][1]; $body = ''; $depth = 0; $started = false;
	for (; $i < count($tokens); $i++) {
		$token = $tokens[$i]; $body .= is_array($token) ? $token[1] : $token;
		if ($token === '{') { $depth++; $started = true; }
		if ($token === '}' && --$depth === 0 && $started) { break; }
	}
	$methods[$name] = $body;
}
check(count($methods) === 2, 'Native method extraction failed');
eval('class NativeFulfillmentOrder extends CommandeFournisseur {
	public $statut = 0;
	public $context = array();
	public $error = "";
	public $errors = array();
	const STATUS_ORDERSENT = 3;
	const STATUS_RECEIVED_PARTIALLY = 4;
	const STATUS_RECEIVED_COMPLETELY = 5;
	const STATUS_CANCELED_AFTER_ORDER = 7;
	public function call_trigger($event, $user) {
		global $langs, $conf;
		$trigger = new InterfaceLmdbSupplierOrderLimitTriggers($this->db);
		$result = $trigger->runTrigger($event, $this, $user, $langs, $conf);
		$this->error = $trigger->error;
		return $result;
	}
'.implode("\n", $methods).'}');

class FulfillmentDb extends TestDb
{
	public $commits = 0;
	public $rollbacks = 0;
	private $snapshot;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function begin() { $this->snapshot = serialize(array($this->native, $this->entry)); $this->transaction_opened++; return 1; }
	public function commit() { $this->commits++; $this->transaction_opened--; return 1; }
	public function rollback() { list($this->native, $this->entry) = unserialize($this->snapshot); $this->rollbacks++; $this->transaction_opened--; return 1; }
	public function lasterror() { return 'Simulated SQL error'; }
	public function query($sql) {
		if (preg_match('/^UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_statut = (\d+)/', $sql, $match)) {
			$this->queries[] = $sql;
			$this->native->fk_statut = (int) $match[1];
			return new TestResult(array());
		}
		$result = parent::query($sql);
		if ($result && strpos($sql, 'UPDATE '.MAIN_DB_PREFIX.'lmdbsupplierorderlimit_consumption SET active = 0') === 0 && $this->entry) { $this->entry->active = 0; }
		return $result;
	}
}
function fulfillmentCase($status, $entity = 1) {
	resetCase();
	$db = new FulfillmentDb(); $db->transaction_opened = 0; $db->ready = 0; $db->unresolved = true;
	$db->rules = array((object) rule(1, 'order', '0'), (object) rule(2, 'day', '0'));
	$db->native = (object) array('rowid'=>1,'entity'=>$entity,'fk_soc'=>5,'fk_projet'=>0,'fk_statut'=>$status,'total_ht'=>'40');
	$db->entry = (object) array('entity'=>$entity,'fk_supplier_order'=>1,'fk_project'=>0,'active'=>1,'unresolved'=>1,'snapshot_amount_ht'=>null,'fk_user'=>10,'date_approval'=>null);
	$order = new NativeFulfillmentOrder($db); $order->entity = $entity; $order->status = $status; $order->statut = $status;
	$user = new TestUser(); $user->id = 20; $user->denied[] = 'fournisseur.commande.approuver';
	return array($db, $order, $user);
}
$cases = 0;
foreach (array(0,1,2,3,4,5,6,7,9,null,'','3x',true,2.5,-1) as $previous) {
	foreach (array(3,4,5) as $target) {
		list($db,$order,$user) = fulfillmentCase($previous);
		$before = serialize($db->entry); $result = $order->setStatus($user, $target);
		$allowed = in_array($previous, array(2,3,4,5), true);
		check(($result > 0) === $allowed, 'setStatus approval boundary');
		check($db->native->fk_statut === ($allowed ? $target : $previous), 'setStatus commit/rollback');
		check($db->rollbacks === ($allowed ? 0 : 1) && $db->commits === ($allowed ? 1 : 0), 'native transaction result');
		check(serialize($db->entry) === $before, 'receiver never charged'); $cases++;
	}
}
foreach (array('par','tot','can','nev') as $type) {
	foreach (array('active','missing','inactive','sql_failure','wrong_entity','no_permission') as $scenario) {
		list($db,$order,$user) = fulfillmentCase(3, $scenario === 'wrong_entity' ? 2 : 1);
		if ($scenario === 'missing') { $db->entry = null; }
		if ($scenario === 'inactive') { $db->entry->active = 0; }
		if ($scenario === 'sql_failure') { $db->fail = 'SET active = 0'; }
		if ($scenario === 'no_permission') { $user->denied[] = 'fournisseur.commande.receptionner'; }
		$before = serialize($db->entry); $cancel = in_array($type, array('can','nev'), true);
		$result = $order->Livraison($user, dol_now(), $type, '');
		$allowed = !in_array($scenario, array('wrong_entity','no_permission'), true) && !($cancel && $scenario === 'sql_failure');
		check(($result > 0) === $allowed, 'Livraison '.$type.' / '.$scenario);
		check($db->native->fk_statut === ($allowed ? ($cancel ? 7 : ($type === 'par' ? 4 : 5)) : 3), 'Livraison transaction result');
		if ($allowed && $cancel) {
			check(!$db->entry || $db->entry->active === 0, 'cancellation releases accounting');
			check($order->Livraison($user, dol_now(), $type, '') > 0, 'cancellation replay');
		} else { check(serialize($db->entry) === $before, 'receipt or rollback preserves accounting'); }
		check($db->transaction_opened === 0, 'transaction closed'); $cases++;
	}
}
list($db,$order,$user) = fulfillmentCase(3);
$order->statut = 0;
check($order->setStatus($user, 4) < 0 && $db->native->fk_statut === 3 && $db->rollbacks === 1, 'inconsistent previous aliases refused'); $cases++;
list($db,$order,$user) = fulfillmentCase(3, 2);
$entities['supplier_order'] = '1,2';
check($order->Livraison($user, dol_now(), 'can', '') > 0, 'shared owner cancellation');
check((bool) preg_grep('/SET active = 0 WHERE entity = 2 AND fk_supplier_order = 1$/', $db->queries), 'release restricted to owner entity'); $cases++;
list($db,$order,$user) = fulfillmentCase(3);
$receptionEnabled = true; $user->denied[] = 'fournisseur.commande.receptionner';
check($order->Livraison($user, dol_now(), 'par', '') > 0, 'native reception module permission sufficient'); $cases++;
list($db,$order,$user) = fulfillmentCase(3);
$receptionEnabled = true; $user->denied[] = 'reception.creer';
check($order->Livraison($user, dol_now(), 'tot', '') < 0 && $db->native->fk_statut === 3, 'native reception module permission required'); $cases++;
echo "$cases native method scenarios passed; SQL/transactions simulated; source SHA256 ".hash_file('sha256', $sourcePath)."\n";
