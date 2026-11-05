<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;
use System\Core\Router;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Runner - registry, hooks, and execution.
 *
 * Bridges Boot (autoloader) and Router (dispatch).
 * Loaded manually by Boot AFTER the autoloader is registered,
 * so its own dependencies resolve through the autoloader.
 */
final class Runner
{
    private static ?self $instance = null;

    private function __construct ()
    {
        $this->initHooks ();
    }

    public static function instance (): self
    {
        if (is (self::$instance, null))
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks (): void
    {
        // Load global helper functions - file list from Config/helper.conf.php
        foreach (get_config ('helper') as $stem)
        {
            $helper_file = interlace (Registry::get ('helpers'), "{$stem}.helper.php");
            if (is_readable ($helper_file)) {require_once $helper_file;}
        }

        // Activation/Deactivation - use plugin_key from Registry
        $plugin_key = Registry::get ('plugin_key');
        register_activation_hook ($plugin_key, [$this, 'activate']);
        register_deactivation_hook ($plugin_key, [$this, 'deactivate']);
        register_uninstall_hook ($plugin_key, [__CLASS__, 'uninstall']);

        // Core routing interception - parse_request runs early
        if (function_exists ('add_action')) {
            add_action ('parse_request', [$this, 'handleRouting'], 10, 2);
            add_action ('flush_rewrite_rules', [$this, 'flushRewriteRules']);
            add_action ('admin_notices', [$this, 'adminNotices']);
        }
        if (function_exists ('add_filter')) {
            add_filter ('redirect_canonical', [$this, 'filterCanonicalRedirect'], 10, 2);
        }
    }

    /**
     * Prevent WordPress canonical redirect for our routes.
     * WordPress's redirect_canonical fires on template_redirect and can
     * conflict with our routes that don't have trailing slashes.
     *
     * @param string $redirect_url The redirect URL WordPress wants to use
     * @param string $requested_url The originally requested URL
     * @return string|false False to prevent redirect, URL to allow redirect
     */
    public function filterCanonicalRedirect (string $redirect_url, string $requested_url): string|false
    {
        $home_url = home_url ('/');
        $our_routes = [
            $home_url . 'login',
            $home_url . 'login/',
            $home_url . 'auth',
            $home_url . 'auth/',
            $home_url . 'welcome',
            $home_url . 'welcome/',
        ];

        foreach ($our_routes as $route) {
            // Match both with and without trailing slash
            if (rtrim($requested_url, '/') === rtrim($route, '/')) {
                return false; // Don't redirect our routes
            }
        }

        return $redirect_url; // Allow redirect for other URLs
    }

    public function activate (): void
    {
        $this->flushRewriteRules ();
        // Create modules directory if not exists
        $modules_path = Registry::get ('modules');
        if (!is_dir ($modules_path)) {wp_mkdir_p ($modules_path);}
    }

    public function deactivate(): void
    {
        $this->flushRewriteRules ();
    }

    public static function uninstall(): void
    {
        // Clean up transients
        delete_transient ('radykfw_modules_cache');
        delete_transient ('radykfw_routes_cache');
    }

    public function flushRewriteRules (): void
    {
        flush_rewrite_rules ();
    }

    /**
         * Main routing handler - intercepts requests before WordPress
         *
         * @param \WP $wp WP instance
         * @return void
         */
        public function handleRouting (\WP $wp): void
        {
            // Get request path (from WP, already rewritten)
            $path = $this->getRequestPath ($wp);

            // Check route aliases FIRST using original request URI (before WP rewrite)
            // Aliases always take priority - they are explicit route claims
            $handled = $this->tryAliases ();

            if ($handled) {
                // Alias matched and dispatched - exit immediately
                $wp->query_vars['radykfw_handled'] = true;
                exit;
            }

            // Skip admin, ajax, rest, and static files
            if ($this->shouldSkipRouting ($path, $wp)) {return;}

            $routingNative = config('routing.native', true);

            if ($routingNative) {
                // Priority: WordPress first → HMVC → 404(WP)
                // Check if WP has native match (post, page, term, etc.)
                if ($this->isWpNativePath($wp, $path)) {
                    return; // Let WordPress handle it
                }
                // WP doesn't have native match → try HMVC auto-discovery
                $handled = Router::handle (explode ('/', $path));
            } else {
                // Priority: HMVC first → WordPress → 404(WP)
                // Try HMVC auto-discovery first
                $handled = Router::handle (explode ('/', $path));
                if (!$handled) {
                    // HMVC didn't claim it → let WordPress handle
                    return;
                }
            }

            if ($handled) {
                $wp->query_vars['radykfw_handled'] = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("RadykFW: Route handled, exiting");
                }
                exit;
            }
        }

        /**
         * Try to dispatch via route aliases.
         *
         * @return bool True if alias matched and dispatched
         */
        private function tryAliases (): bool
        {
            $aliases = config ('routing.aliases', []);
            $httpMethod = strtoupper (catch_server ('request.method', 'get'));
        
            // Use original request URI for alias matching (before WP rewrite)
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $requestPath = parse_url($requestUri, PHP_URL_PATH);
            $requestPath = trim($requestPath, '/');
        
            // Strip plugin base path (e.g. /radykfwtest/login → login)
            $pluginBase = trim(parse_url(home_url('/'), PHP_URL_PATH), '/');
            if (!empty($pluginBase) && str_starts_with($requestPath, $pluginBase . '/')) {
                $requestPath = substr($requestPath, strlen($pluginBase) + 1);
            }

            $aliasKey = "{$httpMethod} /" . $requestPath;

                        if (isset ($aliases[$aliasKey])) {
                            $aliasTarget = $aliases[$aliasKey];
                            // Parse alias target: "Module\Controller::method" (explicit) or "Module::method" (shorthand)
                            if (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)\\\\([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $aliasTarget, $matches)) {
                                // Explicit: Module\Controller::method
                                $module = $matches[1];
                                $controller = $matches[2];
                                $method = $matches[3];
                                $controllerClass = "Modules\\{$module}\\Controllers\\{$controller}";
                                Router::setCurrentModule ($module);
                                if (Router::tryDispatch($controllerClass, $method, [])) {
                                    return true;
                                }
                            } elseif (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $aliasTarget, $matches)) {
                                // Shorthand: Module::method (controller = module)
                                $module = $matches[1];
                                $method = $matches[2];
                                $controller = $module;
                                $controllerClass = "Modules\\{$module}\\Controllers\\{$controller}";
                                Router::setCurrentModule ($module);
                                if (Router::tryDispatch($controllerClass, $method, [])) {
                                    return true;
                                }
                            }
                        }

                        return false;
        }

        private function getRequestPath (\WP $wp): string
        {
            $request = isset ($wp->request) ? $wp->request : '';
            $path = trim ($request, '/');

            // Return empty string for root path - let Router decide
            return $path;
        }

        /**
         * Check if the current request is a WordPress native path
         * (post, page, term, archive, search, etc.)
         *
         * @param \WP $wp WP instance
                 * @param string $path Request path
                 * @return bool True if WP has a native match
                 */
                private function isWpNativePath (\WP $wp, string $path): bool
                {
                    // If WP has query vars for native content types, it's a WP path
                    $nativeQueryVars = [
                        'p', 'page_id', 'attachment_id', 'name', 'pagename',
                        'category_name', 'tag', 'taxonomy', 'term',
                        'author', 'author_name', 'year', 'monthnum', 'day',
                        's', 'search', 'feed', 'post_type',
                    ];

                    foreach ($nativeQueryVars as $var) {
                        if (!empty($wp->query_vars[$var])) {
                            return true;
                        }
                    }

                    // Check if it's a known WP path that should not be intercepted
                    $wpPaths = config('routing.skip_paths', []);
                    if (in_array($path, $wpPaths, true)) {
                        return true;
                    }

                    return false;
                }

    private function shouldSkipRouting (string $path, \WP $wp): bool
    {
        // Skip admin
        if (is_admin () && !wp_doing_ajax ()) {return true;}

        // Skip AJAX
        if (wp_doing_ajax ()) {return true;}

        // Skip REST API
        if (str_starts_with ($path, 'wp-json/') || str_starts_with ($path, 'rest/')) {return true;}

        // Skip login/wp-admin related (WP paths tanpa .php)
                // Also skip short forms (login, signup, activate) for WP native paths
                if (in_array ($path, ['wp-login', 'wp-signup', 'wp-activate', 'login', 'signup', 'activate'], true)) {return true;}

        // Skip static files (has extension)
        if (preg_match ('/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot|map)$/i', $path)) {return true;}

        // Skip if already handled by RadykFW
        if (isset ($wp->query_vars['radykfw_handled'])) {return true;}

        return false;
    }

    public function adminNotices (): void
    {
        // global $wp_version;

        // Check PHP version
        if (version_compare (PHP_VERSION, '8.1', '<'))
        {
            echo '<div class="notice notice-error"><p><strong>RadykFW:</strong> Requires PHP 8.1 or higher. Current: ' . PHP_VERSION . '</p></div>';
        }

        // Check WP version
        if (version_compare (Registry::get ('wp_version'), '6.0', '<'))
        {
            echo '<div class="notice notice-warning"><p><strong>RadykFW:</strong> Recommended WordPress 6.0+. Current: ' . Registry::get ('wp_version') . '</p></div>';
        }
    }
}
