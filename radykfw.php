<?php
/**
 * Plugin Name: RadykFW
 * Plugin URI: https://radyklabs.com/radykfw
 * Description: WordPress HMVC Framework with custom routing. Intercepts requests and routes to modules before WordPress native handling.
 * Version: 1.0.0
 * Author: RadykLabs
 * Author URI: https://radyklabs.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: radykfw
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('RADYKFW_VERSION', '1.0.0');
define('RADYKFW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RADYKFW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RADYKFW_MODULES_DIR', RADYKFW_PLUGIN_DIR . 'modules/');
define('RADYKFW_SYSTEM_DIR', RADYKFW_PLUGIN_DIR . 'system/');
define('RADYKFW_ASSETS_DIR', RADYKFW_PLUGIN_DIR . 'assets/');
define('RADYKFW_ASSETS_URL', RADYKFW_PLUGIN_URL . 'assets/');

// Autoloader
require_once RADYKFW_PLUGIN_DIR . 'vendor/autoload.php';

use RadykFW\Core\Application;
use RadykFW\Core\Router;
use RadykFW\Services\CacheService;

/**
 * Main RadykFW Plugin Class
 */
final class RadykFW {
    private static ?self $instance = null;
    private Application $app;
    private Router $router;
    private CacheService $cache;

    private function __construct() {
        $this->initHooks();
        $this->bootstrap();
    }

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void {
        // Activation/Deactivation
        register_activation_hook(RADYKFW_PLUGIN_DIR . 'radykfw.php', [$this, 'activate']);
        register_deactivation_hook(RADYKFW_PLUGIN_DIR . 'radykfw.php', [$this, 'deactivate']);
        register_uninstall_hook(RADYKFW_PLUGIN_DIR . 'radykfw.php', [__CLASS__, 'uninstall']);

        // Core routing interception - parse_request runs early
        add_action('parse_request', [$this, 'handleRouting'], 10, 2);

        // Flush rewrite rules on permalink save
        add_action('flush_rewrite_rules', [$this, 'flushRewriteRules']);

        // Admin notices
        add_action('admin_notices', [$this, 'adminNotices']);
    }

    private function bootstrap(): void {
        $this->cache = new CacheService();
        $this->router = new Router($this->cache);
        $this->app = new Application($this->router, $this->cache);
        $this->app->boot();
    }

    public function activate(): void {
        $this->flushRewriteRules();
        $this->cache->flush();
        // Create modules directory if not exists
        if (!is_dir(RADYKFW_MODULES_DIR)) {
            wp_mkdir_p(RADYKFW_MODULES_DIR);
        }
    }

    public function deactivate(): void {
        $this->flushRewriteRules();
    }

    public static function uninstall(): void {
        // Clean up transients
        delete_transient('radykfw_modules_cache');
        delete_transient('radykfw_routes_cache');
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function flushRewriteRules(): void {
        flush_rewrite_rules();
    }

    /**
     * Main routing handler - intercepts requests before WordPress
     *
     * @param WP $wp WP instance
     * @param array $extra_query_vars Extra query vars
     * @return void
     */
    public function handleRouting(WP $wp, array $extra_query_vars = []): void {
        // Get request path
        $path = $this->getRequestPath($wp);

        // Skip admin, ajax, rest, and static files
        if ($this->shouldSkipRouting($path, $wp)) {
            return;
        }

        // Try to match route to module
        $match = $this->router->match($path);

        if ($match !== null) {
            // Module found - dispatch and stop WordPress
            $this->dispatchModule($match);
            // Prevent WordPress from continuing
            $wp->query_vars['radykfw_handled'] = true;
            // We need to exit cleanly - use template_redirect to stop
            add_action('template_redirect', function() {
                exit;
            }, 1);
        }
        // If no match, return false (implicit) - WordPress handles natively
    }

    private function getRequestPath(WP $wp): string {
        // Get the request path from WP
        $request = isset($wp->request) ? $wp->request : '';

        // Clean up path
        $path = trim($request, '/');

        // Handle empty path (home)
        if (empty($path)) {
            $path = 'home';
        }

        return $path;
    }

    private function shouldSkipRouting(string $path, WP $wp): bool {
        // Skip admin
        if (is_admin() && !wp_doing_ajax()) {
            return true;
        }

        // Skip AJAX
        if (wp_doing_ajax()) {
            return true;
        }

        // Skip REST API
        if (str_starts_with($path, 'wp-json/') || str_starts_with($path, 'rest/')) {
            return true;
        }

        // Skip login/wp-admin related
        if (in_array($path, ['wp-login.php', 'wp-signup.php', 'wp-activate.php'], true)) {
            return true;
        }

        // Skip static files (has extension)
        if (preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot|map)$/i', $path)) {
            return true;
        }

        // Skip if already handled by RadykFW
        if (isset($wp->query_vars['radykfw_handled'])) {
            return true;
        }

        return false;
    }

    private function dispatchModule(array $match): void {
        try {
            $module = $match['module'];
            $controllerClass = $match['controller'];
            $method = $match['method'] ?? 'index';
            $params = $match['params'] ?? [];

            // Load module
            $controller = $this->app->getModuleLoader()->load($module, $controllerClass);

            if ($controller !== null && method_exists($controller, $method)) {
                // Execute controller method
                call_user_func_array([$controller, $method], $params);
            } else {
                // Controller/method not found - let WordPress handle
                error_log("RadykFW: Controller {$controllerClass} or method {$method} not found in module {$module}");
            }
        } catch (Throwable $e) {
            error_log("RadykFW Dispatch Error: " . $e->getMessage());
            // On error, let WordPress handle
        }
    }

    public function adminNotices(): void {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            echo '<div class="notice notice-error"><p><strong>RadykFW:</strong> Requires PHP 8.1 or higher. Current: ' . PHP_VERSION . '</p></div>';
        }

        // Check WP version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            echo '<div class="notice notice-warning"><p><strong>RadykFW:</strong> Recommended WordPress 6.0+. Current: ' . $wp_version . '</p></div>';
        }
    }

    public function getRouter(): Router {
        return $this->router;
    }

    public function getApplication(): Application {
        return $this->app;
    }
}

// Initialize plugin
RadykFW::instance();

/**
 * Helper function to get RadykFW instance
 *
 * @return RadykFW
 */
function radykfw(): RadykFW {
    return RadykFW::instance();
}

/**
 * Get module path
 *
 * @param string $module Module name/path
 * @return string
 */
function module_path(string $module = ''): string {
    $base = RADYKFW_MODULES_DIR;
    return $module ? trailingslashit($base) . ltrim($module, '/') : $base;
}

/**
 * Get system path
 *
 * @param string $path Relative path
 * @return string
 */
function system_path(string $path = ''): string {
    $base = RADYKFW_SYSTEM_DIR;
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Get asset URL
 *
 * @param string $path Relative path
 * @return string
 */
function asset_url(string $path = ''): string {
    $base = RADYKFW_ASSETS_URL;
    return $path ? trailingslashit($base) . ltrim($path, '/') : $base;
}

/**
 * Render view
 *
 * @param string $view View path (module/view or system view)
 * @param array $data Data to pass to view
 * @param bool $return Return instead of echo
 * @return string|void
 */
function view(string $view, array $data = [], bool $return = false): string|null {
    extract($data);

    // Determine view file
    $viewFile = '';

    if (str_starts_with($view, 'modules/')) {
        // Module view: modules/login/View/index.php
        $parts = explode('/', $view);
        $module = $parts[1] ?? '';
        $viewName = $parts[3] ?? 'index'; // modules/{module}/View/{view}
        $viewFile = module_path($module) . "/View/{$viewName}.php";
    } elseif (str_starts_with($view, 'system/')) {
        // System view: system/views/errors/404.php
        $viewFile = system_path("views/{$view}.php");
    } else {
        // Default to system views
        $viewFile = system_path("views/{$view}.php");
    }

    if (!file_exists($viewFile)) {
        // Fallback to 404 view
        $viewFile = system_path('views/errors/404.php');
    }

    ob_start();
    include $viewFile;
    $content = ob_get_clean();

    if ($return) {
        return $content;
    }

    echo $content;
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
        $configFile = system_path('config/app.php');
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