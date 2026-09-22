<?php
// Read-only source-level smoke test; not an installed Dolibarr instance.
$root = $argv[1] ?? '';
if (!is_file($root.'/core/lib/functions.lib.php')) { fwrite(STDERR, "Usage: php test/native_smoke.php /path/to/dolibarr/htdocs\n"); exit(2); }
define('DOL_DOCUMENT_ROOT', $root);
$conf = (object) array('global' => (object) array('MAIN_MAX_DECIMALS_TOT' => 2, 'MAIN_MAX_DECIMALS_UNIT' => 5));
$langs = null;
require $root.'/core/lib/functions.lib.php';
require __DIR__.'/../class/lmdbsupplierorderlimitpolicy.class.php';
foreach (array(2 => '12.35000000', 3 => '12.34600000', 0 => '12.00000000') as $decimals => $expected) {
	$conf->global->MAIN_MAX_DECIMALS_TOT = $decimals;
	$actual = LmdbSupplierOrderLimitPolicy::amount('12.3456');
	if ($actual !== $expected) { throw new RuntimeException('Native MT mismatch: '.$actual.' / '.$expected); }
}
foreach (array('', 'abc', 'abc123', '-1', null, true) as $invalid) {
	if (LmdbSupplierOrderLimitPolicy::amount($invalid) !== null) { throw new RuntimeException('Invalid amount accepted'); }
}
echo "Native price2num MT smoke OK (PHP ".PHP_VERSION.")\n";

// Descriptor settings: native readers, simulated persistence, no database connection.
$saved = array();
function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity) {
	global $saved, $conf;
	$saved[$name] = array('value'=>$value,'entity'=>$entity);
	$conf->global->{$name} = $value;
	return 1;
}
$conf->entity = 2;
$conf->file = (object) array('dol_document_root'=>array(dirname(__DIR__, 2)));
require __DIR__.'/../class/actions_lmdbsupplierorderlimit.class.php';
require __DIR__.'/../core/modules/modLmdbSupplierOrderLimit.class.php';
$module = new modLmdbSupplierOrderLimit(new stdClass());
$conf->global->LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS = '0';
$conf->global->LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE = '';
$initDefaults = new ReflectionMethod($module, 'initDefaultConstants');
$initDefaults->setAccessible(true);
$initDefaults->invoke($module);
if (isset($saved['LMDBSUPPLIERORDERLIMIT_LOG_ALLOWED_APPROVALS']) || isset($saved['LMDBSUPPLIERORDERLIMIT_SHOW_DENIED_MESSAGE'])) { throw new RuntimeException('Existing zero/empty setting overwritten'); }
if (($saved['LMDBSUPPLIERORDERLIMIT_DAY_MODE']['value'] ?? '') !== 'civil') { throw new RuntimeException('Missing default'); }
$saved = array();
$initDefaults->invoke($module);
if ($saved) { throw new RuntimeException('Defaults not idempotent'); }
$conf->global->MULTICOMPANY_EXTERNAL_MODULES_SHARING = json_encode(array('other_module'=>array('keep'=>1),'lmdbsupplierorderlimit'=>array('sharingelements'=>array('lmdbsupplierorderlimit_limit'=>array('input'=>array('global'=>array('hide'=>false)))))));
$sharing = new ReflectionMethod($module, 'persistSharing');
$sharing->setAccessible(true);
$sharing->invoke($module);
$first = $saved['MULTICOMPANY_EXTERNAL_MODULES_SHARING'];
$sharing->invoke($module);
$payload = json_decode($first['value'], true);
if ($first !== $saved['MULTICOMPANY_EXTERNAL_MODULES_SHARING'] || $first['entity'] !== 2 || $payload['other_module']['keep'] !== 1 || $payload['lmdbsupplierorderlimit']['sharingelements']['lmdbsupplierorderlimit_limit']['input']['global']['hide'] !== false) { throw new RuntimeException('Sharing choices lost'); }
echo "Descriptor preservation smoke OK (native readers, simulated writes)\n";
