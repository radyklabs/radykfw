<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Registry - Single source of truth for boot-time paths & constants
 * Replaces global constants from foreach ($radykfw) $set($k, $v)
 * Old logic (preserved as comment):
 * foreach ($radykfw as $k => $v) {$set ($k, $v);}
 * foreach ($file_extension as $k => $e) {$set ($k, $e);}
 */
final class Registry
{
    /**
     * Folder → file-extension maps. SINGLE SOURCE OF TRUTH for autoloading.
     *
     * @var array<string, array<string,string>>
     */
    public const ext_maps = [
        // system/<Folder>/<Class> → extension (by folder)
        'system' => [
            'Contracts' => '.contract.php',
            'Config'    => '.conf.php',
            'Enums'     => '.enum.php',
            'Helpers'   => '.helper.php',
            'Services'  => '.service.php',
            'Views'     => '.view.php',
            'Core'      => '.core.php'
        ],
        // modules/<Module>/<Folder>/<Class> → extension (by folder)
        'modules' => [
            'Controllers' => '.controller.php',
            'Models'      => '.model.php',
            'Services'    => '.service.php',
            'Views'       => '.view.php'
        ],
    ];

    private static array $data = [];

    private static bool $initialized = false;

    /**
     * Initialize the registry.
     *
     * Default: read the constants defined by .starter (the key file) —
     *   keyfile, modules_dir, system_dir, core_dir — plus WP context
     *   (WP_PLUGIN_DIR, $wp_version). .starter is the single source of truth
     *   for directory names; Registry only derives paths/namespaces from them.
     *
     * Optional override: pass a pre-built $data array (e.g. from tests/CLI)
     * to skip the constant read entirely.
     *
     * @param array<string,mixed>|null $data Pre-built registry data; null = build from .starter constants
     */
    public static function instance (?array $data = null): void
    {
        if (self::$initialized) {return;}

        self::$data = $data ?? self::buildFromStarter ();
        self::$initialized = true;
    }

    /**
     * Build the registry data from .starter constants + WP context.
     * Single source of truth lives in .starter; this only derives.
     *
     * @return array<string,mixed>
     */
    private static function buildFromStarter (): array
    {
        global $wp_version;

        $radykfw['version']     = version;
        $radykfw['textdomain']  = textdomain;

        $radykfw['plugin_path'] = plugin_dir_path (keyfile);
        $radykfw['plugin_url']  = plugin_dir_url (keyfile);
        $radykfw['plugin_base'] = plugin_basename (keyfile);
        $radykfw['plugin_key']  = interlace (WP_PLUGIN_DIR, $radykfw['plugin_base']);


        $radykfw['wp_version']  = $wp_version;
        $radykfw['ext_maps'] = self::ext_maps;

        // Paths + namespaces derived from the dir constants
        $modules_dir = defined ('modules_dir') ? modules_dir : 'modules';
        $radykfw['modules']  = interlace ($radykfw['plugin_path'], $modules_dir);
        $radykfw['nsmod']    = ucfirst ("{$modules_dir}\\");

        $system_dir = defined ('system_dir') ? system_dir : 'system';
        $radykfw['system']   = interlace ($radykfw['plugin_path'], $system_dir);
        $radykfw['nssys']    = ucfirst ("{$system_dir}\\");

        $core_dir = defined ('core_dir') ? core_dir : 'Core';
        $radykfw['core']     = interlace ($radykfw['system'], $core_dir);
        $radykfw['nscore']   = $radykfw['nssys'] . "{$core_dir}\\";

        $helper_dir = defined ('helper_dir') ? helper_dir : 'Helpers';
        $radykfw['helpers']  = interlace ($radykfw['system'], $helper_dir);

        $config_dir = defined ('config_dir') ? config_dir : 'Config';
        $radykfw['config']  = interlace ($radykfw['system'], $config_dir);

        return $radykfw;
    }

    public static function get (string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function has (string $key): bool
    {
        return array_key_exists ($key, self::$data);
    }

    public static function all (): array
    {
        return self::$data;
    }

    /**
     * Resolve the file extension for a namespace folder.
     *
     * @param string $root   'system' | 'modules'
     * @param string $folder folder name (e.g. Contracts, Controllers, Core)
     * @return string file extension
     */
    public static function ext (string $folder, string $root = system_dir): string
    {
        // $map = self::ext_maps[$root] ?? [];
        return self::ext_maps[$root][$folder]; //?? $map['_default'] ?? '.php';
    }
}
