<?php declare (strict_types=1);

/**
 * RadykFW test bootstrap (pure-PHP).
 *
 * Registers a minimal PSR-4 autoloader and the bare functions that
 * `get_config()` depends on, so config + pure-utility logic can be
 * exercised without a full WordPress runtime.
 */

// Stub the WordPress constant + plugin helper functions that the
// framework's config accessor relies on. Only definitions that are
// absent are filled in — never override a real WP runtime.
if (!defined ('ABSPATH')) {
    define ('ABSPATH', __DIR__ . '/');
}

if (!defined ('keyfile')) {
    define ('keyfile', __DIR__ . '/../plugin.init.php');
}

// `sep` is normally set in plugin.init.php via $set(); pathify() in .supports
// depends on it, so backfill it for the pure-PHP test context.
if (!defined ('sep')) {
    define ('sep', DIRECTORY_SEPARATOR);
}

if (!defined ('modules_dir')) {
    define ('modules_dir', 'modules');
}

if (!defined ('system_dir')) {
    define ('system_dir', 'system');
}

if (!function_exists ('plugin_dir_path')) {
    // WP convention: returns the plugin directory path (with trailing slash).
    function plugin_dir_path (string $file): string {
        return dirname ($file) . '/';
    }
}

// Load the support library that defines `interlace()`, `pathify()`, and
// `get_config()`. `.supports` is owned by the plugin — never redefine its
// functions here.
$supports = plugin_dir_path (keyfile) . '.supports';
if (is_readable ($supports)) {require_once $supports;}

// Shared WP stubs/fakes + Router dispatch fixtures (guarded; no-op under real WP).
require_once __DIR__ . '/support/wp-stubs.php';
require_once __DIR__ . '/support/fixtures.php';
require_once __DIR__ . '/support/model-fixtures.php';

// Pure-PHP global helper stubs (module_path, system_path, current_module,
// trailingslashit) so namespaced services resolve these in the global
// namespace without a WordPress runtime. Guarded: no-op when real
// definitions are present.
require_once __DIR__ . '/support/drip-stubs.php';

// View helpers: function-only file (no class). Loads after the PSR-4
// autoloader is registered below so Drip::render() inside the helpers
// resolves System\Services\Drip at call time.
require_once __DIR__ . '/../system/Helpers/view.helper.php';

// PSR-4 autoloader: System\Core\* => system/Core/*.core.php,
// System\* => system/*.php, Modules\* => modules/*.php
// ponytail: registry-driven lookup (Registry::ext) requires .starter constants;
// the static map below covers every ext_maps entry, upgrade to registry-driven
// resolution if ext maps grow.
spl_autoload_register (static function (string $class): void {
    $rules = [
        // prefix => [baseDir, fileSuffix]
        'System\\Core\\'       => ['system/Core/', 'core'],
        'System\\Middleware\\' => ['system/Middleware/', 'middleware'],
        'System\\Services\\'   => ['system/Services/', 'service'],
        'System\\Helpers\\'    => ['system/Helpers/', 'helper'],
        'System\\Contracts\\'  => ['system/Contracts/', 'contract'],
        'System\\'             => ['system/', ''],
        'Modules\\'            => ['modules/', ''],
        // ponytail: Router tests set nsmod to 'Tests\Ci\' so controller
        // classes resolve to fixtures declared in tests/support/fixtures.php;
        // that file is required explicitly in setUp instead of autoload.
    ];

    foreach ($rules as $prefix => [$baseDir, $suffix]) {
        if (!str_starts_with ($class, $prefix)) {continue;}

        $rel  = str_replace ('\\', '/', substr ($class, strlen ($prefix)));
        $name = $rel . ($suffix !== '' ? ".{$suffix}" : '') . '.php';
        $path = __DIR__ . '/../' . $baseDir . $name;
        if (is_readable ($path)) {require $path;}
        return;
    }
});
