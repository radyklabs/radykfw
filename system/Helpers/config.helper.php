<?php
/**
 * RadykFW Config Helper
 * File: system/Helpers/config.helper.php
 *
 * Access configuration values from any file under system/Config/*.conf.php
 * using dot-notation.
 *
 *   config('app.debug')              -> app.conf.php['app']['debug']
 *   config('auth.post_login_url')    -> app.conf.php['auth']['post_login_url']
 *   config('routing', 'skip_paths')  -> routing.conf.php['skip_paths']
 *   config('database', 'connection') -> database.conf.php['connection']
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a config value by dot-notation from a named config file.
 *
 * @param string $key      Dot-notation key within the config file
 *                         (e.g. 'app.debug', 'routing.skip_paths')
 * @param mixed  $default  Default value if key not found
 * @param string $file     Config file name without extension
 *                         (default 'app' -> app.conf.php)
 * @return mixed
 */
function config(string $key, mixed $default = null, string $file = 'app'): mixed {
    static $cache = [];

    if (!isset($cache[$file])) {
        $path = system_path("Config/{$file}.conf.php");
        $cache[$file] = is_readable($path) ? require $path : [];
    }

    $segments = explode('.', $key);
    $value = $cache[$file];

    foreach ($segments as $seg) {
        if (is_array($value) && array_key_exists($seg, $value)) {
            $value = $value[$seg];
        } else {
            return $default;
        }
    }

    return $value;
}
