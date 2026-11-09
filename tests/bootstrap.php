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
    define ('keyfile', __DIR__ . '/../radykfw.php');
}

// `sep` is normally set in radykfw.php via $set(); pathify() in .supports
// depends on it, so backfill it for the pure-PHP test context.
if (!defined ('sep')) {
    define ('sep', DIRECTORY_SEPARATOR);
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

// PSR-4 autoloader: System\Core\* => system/Core/*.core.php,
// System\* => system/*.php, Modules\* => modules/*.php
spl_autoload_register (static function (string $class): void {
    $rules = [
        // prefix => [baseDir, fileSuffix]
        'System\\Core\\' => ['system/Core/', 'core'],
        'System\\Services\\' => ['system/Services/', 'service'],
        'System\\'       => ['system/', ''],
        'Modules\\'      => ['modules/', ''],
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
