<?php
/**
 * RadykFW Config Helper
 * File: system/Helpers/config.helper.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a config value from system/Config/app.conf.php by dot-notation key.
 *
 * @param string $key     Dot-notation key (e.g. 'routing.skip_paths')
 * @param mixed  $default  Default value if not found
 * @return mixed
 */
function config(string $key, mixed $default = null): mixed {
    static $config = null;

    if ($config === null) {
        $file = system_path('Config/app.conf.php');
        $config = is_readable($file) ? require $file : [];
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $seg) {
        if (is_array($value) && array_key_exists($seg, $value)) {
            $value = $value[$seg];
        } else {
            return $default;
        }
    }

    return $value;
}
