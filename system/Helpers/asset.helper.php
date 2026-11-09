<?php
/**
 * RadykFW Asset Helpers
 * File: system/Helpers/asset.helper.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get asset URL.
 *
 * @param string $path Relative path from assets/
 * @return string
 */
function asset_url(string $path = ''): string {
    $base = \System\Core\Registry::get('plugin_url') . 'assets/';
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Get asset file path.
 *
 * @param string $path Relative path from assets/
 * @return string
 */
function asset_path(string $path = ''): string {
    $base = \System\Core\Registry::get('plugin_path') . '/assets';
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Enqueue a CSS or JS asset with the RadykFW plugin version.
 *
 * @param string $handle    Script/style handle
 * @param string $path      Relative path from assets/
 * @param array  $deps      Dependencies
 * @param bool   $in_footer Load in footer
 */
function radykfw_asset(string $handle, string $path, array $deps = [], bool $in_footer = true): void {
    $url     = asset_url($path);
    $version = \System\Core\Registry::get('version', '1.0.1');

    if (str_ends_with($path, '.js')) {
        wp_enqueue_script($handle, $url, $deps, $version, $in_footer);
    } elseif (str_ends_with($path, '.css')) {
        wp_enqueue_style($handle, $url, $deps, $version);
    }
}
