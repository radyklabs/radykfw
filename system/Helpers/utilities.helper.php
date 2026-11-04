<?php
/**
 * System Helper Functions
 * File: system.helper.php
 *
 * Core system functions for RadykFW
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get RadykFW instance
 *
 * @return \RadykFW
 */
function radykfw(): \RadykFW {
    return \RadykFW::instance();
}

/**
 * Get module path
 *
 * @param string $module Module name/path
 * @return string
 */
function module_path(string $module = ''): string {
    $base = \System\Core\Registry::get ('modules');
    return $module ? trailingslashit($base) . ltrim($module, '/') : $base;
}

/**
 * Get system path
 *
 * @param string $path Relative path
 * @return string
 */
function system_path(string $path = ''): string {
    $base = \System\Core\Registry::get ('system');
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Get asset URL
 *
 * @param string $path Relative path
 * @return string
 */
function asset_url(string $path = ''): string {
    $base = \System\Core\Registry::get ('plugin_url') . 'assets/';
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Get asset file path
 *
 * @param string $path Relative path
 * @return string
 */
function asset_path(string $path = ''): string {
    $base = \System\Core\Registry::get ('plugin_path') . '/assets';
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Render view
 * Usage: view('modules/Login/Views/login.view')
 *        view('system/views/errors/404.error')
 *
 * @param string $view View path
 * @param array $data Data to pass to view
 * @param bool $return Return instead of echo
 * @return string|null
 */
function view(string $view, array $data = [], bool $return = false): ?string {
    // Whitelist-safe: only pass data keys into scope.
    // Prevents variable injection (e.g. $data['__PHP_INI'] or $data['view'] clobber).
    $viewFile = '';
    $parts = explode('/', $view);
    $module = $parts[1] ?? '';
    $viewName = end($parts);

    if (str_starts_with($view, 'modules/')) {
        $viewFile = module_path($module) . '/Views/' . $viewName . '.php';
    } elseif (str_starts_with($view, 'system/')) {
        $viewFile = system_path($view . '.html');
        if (!file_exists($viewFile)) {
            $viewFile = system_path($view . '.php');
        }
    } else {
        $viewFile = system_path('views/' . $view . '.php');
    }

    if (!file_exists($viewFile)) {
        $viewFile = system_path('views/errors/404.error.html');
        if (!file_exists($viewFile)) {
            $viewFile = system_path('views/errors/404.error.php');
        }
    }

    // Build a safe scope: only $data keys, no clobber of internal vars
    $scope = [];
    foreach ($data as $k => $v) {
        $scope[$k] = $v;
    }

    ob_start();
    extract($scope, EXTR_SKIP); // EXTR_SKIP: do NOT overwrite existing vars
    include $viewFile;
    $content = ob_get_clean();

    if ($return) {
        return $content;
    }

    echo $content;
    return null;
}

/**
 * Get config value
 *
 * @param string $key Config key (dot notation)
 * @param mixed $default Default value
 * @return mixed
 */
function config(string $key, mixed $default = null): mixed {
    static $config = null;

    if ($config === null) {
        $configFile = system_path('config/app.conf.php');
        $config = file_exists($configFile) ? require $configFile : [];
    }

    $keys = explode('.', $key);
    $value = $config;

    foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }

    return $value;
}

/**
 * Enqueue asset with version
 *
 * @param string $handle Script/style handle
 * @param string $path Relative path from assets/
 * @param array $deps Dependencies
 * @param bool $in_footer Whether to enqueue in footer
 * @return void
 */
function radykfw_asset(string $handle, string $path, array $deps = [], bool $in_footer = true): void {
    $url = asset_url($path);
    $version = \System\Core\Registry::get ('version', '1.0.1');

    if (str_ends_with($path, '.js')) {
        wp_enqueue_script($handle, $url, $deps, $version, $in_footer);
    } elseif (str_ends_with($path, '.css')) {
        wp_enqueue_style($handle, $url, $deps, $version);
    }
}

/**
 * Log message to RadykFW debug log
 *
 * @param string $message Message
 * @param string $level Log level (debug, info, warning, error)
 * @return void
 */
function radykfw_log(string $message, string $level = 'info'): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $logFile = \System\Core\Registry::get ('plugin_path') . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Check if current request is handled by RadykFW
 *
 * @return bool
 */
function is_radykfw_request(): bool {
    global $wp;
    return isset($wp->query_vars['radykfw_handled']) && $wp->query_vars['radykfw_handled'] === true;
}

/**
 * Get current module name from request
 *
 * @return string|null
 */
function current_module(): ?string {
    global $wp;
    $path = isset($wp->request) ? trim($wp->request, '/') : '';

    if (empty($path)) {
        return null;
    }

    // Get first segment as module
    $parts = explode('/', $path);
    return $parts[0] ?? null;
}

/**
 * Generate URL for a module route
 *
 * @param string $module Module name
 * @param string $path Additional path
 * @return string
 */
function module_url(string $module, string $path = ''): string {
    $base = home_url('/');
    $module = ltrim($module, '/');
    $path = ltrim($path, '/');

    return $base . ($module ? $module . '/' : '') . $path;
}

function dd(mixed ...$vars): never {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        exit; // Use exit instead of return for never-return function
    }

    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;font-family:monospace;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}

/**
 * Dump variables (debug helper)
 *
 * @param mixed ...$vars Variables to dump
 * @return void
 */
function dump(mixed ...$vars): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;font-family:monospace;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
}