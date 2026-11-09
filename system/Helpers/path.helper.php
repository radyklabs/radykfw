<?php
/**
 * RadykFW Path Helpers
 * File: system/Helpers/path.helper.php
 *
 * Functions: module_path(), system_path(), module_url(), current_module()
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get module path.
 *
 * @param string $module Module name (e.g. 'Login')
 * @return string Full path to the module directory
 */
function module_path(string $module = ''): string {
    $base = \System\Core\Registry::get('modules');
    return $module ? trailingslashit($base) . ltrim($module, '/') : $base;
}

/**
 * Get system path.
 *
 * @param string $path Relative path from system/
 * @return string Full path
 */
function system_path(string $path = ''): string {
    $base = \System\Core\Registry::get('system');
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Generate URL for a module route.
 *
 * @param string $module Module name
 * @param string $path   Additional path segments
 * @return string Full URL
 */
function module_url(string $module, string $path = ''): string {
    $module = ltrim($module, '/');
    $path   = ltrim($path, '/');
    return home_url('/' . ($module ? $module . '/' : '') . $path);
}

/**
 * Get current module name from the active request.
 * Set by Router before dispatch.
 *
 * @return string|null
 */
function current_module(): ?string {
    return \System\Core\Router::currentModule() ?: null;
}
