<?php declare (strict_types=1);

/**
 * CLI test: load WP runtime, init RadykFW Runner, call adminNotices().
 * Verify the helper guard notice renders for a class-containing helper.
 *
 *   php tests/HelperGuardCliTest.php
 */
chdir(dirname(__DIR__));

require dirname(__DIR__) . '/../../..//wp-config.php';

$GLOBALS['wp']->init_widgets();
require_once __DIR__ . '/../../.supports';
$do = function ($action, $module = null) {
    $core_file = dirname(__DIR__) . '/system/Core/' . $module . '.core.php';
    require_once $core_file;
    if ($module === 'Runner') {
        System\Core\Runner::instance();
    }
};
$do('initiate_process', 'Boot');

// Trigger adminNotices output
$runner = System\Core\Runner::instance();
ob_start();
$runner->adminNotices();
$out = ob_get_clean();

if (str_contains($out, 'guard-probe') && str_contains($out, 'not loaded')) {
    echo "PASS: admin notice mentions guard-probe helper not loaded\n";
} else {
    echo "FAIL: notice missing. Output:\n$out\n";
    exit(1);
}
