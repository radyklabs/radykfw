<?php declare (strict_types=1);

/**
 * Standalone guard test (no WordPress runtime):
 *
 *   php tests/HelperTypeGuardCliTest.php
 *
 * Loads Boot + Runner, verifies the helper type guard skips a
 * class-containing helper and adminNotices() renders the notice.
 */

chdir(__DIR__ . '/..');

// Stub WP constants Runner.php requires at load time.
define ('ABSPATH', __DIR__ . '/../');
define ('sep', DIRECTORY_SEPARATOR);
define ('keyfile', __DIR__ . '/../radykfw.php');
define ('WP_PLUGIN_DIR', __DIR__ . '/../../../');
define ('WP_DEBUG', true);
define ('version', '1.0.9');
define ('textdomain', 'radykfw');
define ('modules_dir', 'modules');
define ('system_dir', 'system');
define ('core_dir', 'Core');
define ('helper_dir', 'Helpers');
define ('config_dir', 'Config');

// Minimal WP functions needed by Registry::buildFromStarter()
if (!function_exists ('plugin_dir_path')) {
    function plugin_dir_path (string $file): string {return dirname ($file) . '/';}
}
if (!function_exists ('plugin_dir_url')) {
    function plugin_dir_url (string $file): string {return 'http://localhost/';}
}
if (!function_exists ('plugin_basename')) {
    function plugin_basename (string $file): string {return basename (dirname ($file)) . '/' . basename ($file);}
}
// Stub the remaining WP functions Runner::initHooks() calls
if (!function_exists ('register_activation_hook')) {
    function register_activation_hook (string $file, callable $cb): void {}
}
if (!function_exists ('register_deactivation_hook')) {
    function register_deactivation_hook (string $file, callable $cb): void {}
}
if (!function_exists ('register_uninstall_hook')) {
    function register_uninstall_hook (string $file, callable $cb): void {}
}
if (!function_exists ('add_action')) {
    function add_action (string $hook, callable $cb, int $prio = 10, int $args = 1): bool {return true;}
}
if (!function_exists ('add_filter')) {
    function add_filter (string $hook, callable $cb, int $prio = 10, int $args = 1): bool {return true;}
}
if (!function_exists ('home_url')) {
    function home_url (string $path = ''): string {return 'http://localhost' . $path;}
}
if (!function_exists ('esc_html')) {
    function esc_html (string $s): string {return htmlspecialchars ($s, ENT_QUOTES, 'UTF-8');}
}
if (!function_exists ('wp_kses_post')) {
    function wp_kses_post (string $s): string {return $s;}
}

// Stub get_current_screen: switchable per test case.
$GLOBALS['test_current_screen_id'] = 'dashboard';
if (!function_exists ('get_current_screen')) {
    function get_current_screen () {
        $obj = new \stdClass;
        $obj->id = $GLOBALS['test_current_screen_id'] ?? 'unknown';
        return $obj;
    }
}

// Seed $wp_version so Registry::buildFromStarter() picks it up
$GLOBALS['wp_version'] = '6.0';

// Load .supports for get_config() + interlace()
require_once __DIR__ . '/../.supports';

// Registry: build from constants (same as plugin)
require_once __DIR__ . '/../system/Core/Registry.core.php';
System\Core\Registry::instance();

// Boot: register autoloader
require_once __DIR__ . '/../system/Core/Boot.core.php';
System\Core\Boot::instance();

// Runner::instance() triggers initHooks() -> guard scan.
require_once __DIR__ . '/../system/Core/Runner.core.php';
System\Core\Runner::instance();

// Check the guard actually skipped the probe + registered a notice.
$runner = System\Core\Runner::instance();

// Reflect private helper_skips.
$prop = new ReflectionProperty (System\Core\Runner::class, 'helper_skips');
$prop->setAccessible (true);
$skips = $prop->getValue ($runner);

$found = false;
foreach ($skips as $s) {
    if ($s ['file'] === 'guard-probe.helper.php') {$found = true;}
}

if (! $found)
{
    fwrite (STDERR, "FAIL: guard-probe.helper.php not in helper_skips\n");
    exit (1);
}

// Verify a helper-class notice was registered in the global registry.
$regNotices = System\Core\Runner::getAdminNotices ();
$regFound = false;
foreach ($regNotices as $n) {
    if (str_contains ($n ['message'], 'guard-probe') && $n ['in_page'] === ['dashboard', 'plugins']) {$regFound = true;}
}
if (! $regFound)
{
    fwrite (STDERR, "FAIL: helper-class notice not in registry (in_page=['dashboard','plugins']):\n"
        . json_encode ($regNotices, JSON_PRETTY_PRINT) . "\n");
    exit (1);
}

// Gate test: dashboard + plugins = notice shows; post.php = notice hidden.
$cases = [
    ['id' => 'dashboard', 'expect_notice' => true],
    ['id' => 'plugins',   'expect_notice' => true],
    ['id' => 'post',      'expect_notice' => false],
];

$all = [];
foreach ($cases as $c)
{
    $GLOBALS['test_current_screen_id'] = $c ['id'];
    ob_start ();
    $runner->adminNotices ();
    $all [] = [$c, ob_get_clean ()];
}

$failed = 0;
foreach ($all as [$c, $out])
{
    $has_notice = str_contains ($out, 'guard-probe') && str_contains ($out, 'not loaded');
    if ($has_notice !== $c ['expect_notice'])
    {
        fwrite (STDERR, "FAIL screen={$c ['id']}: expected notice=" . var_export ($c ['expect_notice'], true)
            . ", got " . var_export ($has_notice, true) . PHP_EOL);
        $failed++;
    }
    else {echo "ok   screen={$c ['id']} notice_expected=" . var_export ($c ['expect_notice'], true) . PHP_EOL;}
}

if ($failed > 0) {exit (1);}

echo "PASS: guard skipped guard-probe.helper.php + gate verified\n";
echo "skips=" . json_encode ($skips) . "\n";
