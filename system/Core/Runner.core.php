<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;
use System\Core\Router;

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
        // Registry::instance ();
        // call_process ('Registry');
        // $this->initRegistry ();
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

    /**
     * Initialize Registry - single source of truth for all boot-time paths & constants.
     * Reads the dir constants defined in .starter (key file), populates Registry.
     * Registry class was already required by Boot (pre-autoload exception).
     */
    private function initRegistry(): void
    {
        // .starter is the key file: it defines modules_dir, system_dir, core_dir, keyfile.
        // Registry reads those constants directly - Runner does not re-derive anything.
        // Registry::init ();
    }

    private function initHooks(): void
    {
        // Load global helper functions - file list from Config/helper.conf.php
        foreach (get_config ('helper') as $stem)
        {
            $helper_file = interlace (Registry::get ('helpers'), "{$stem}.helper.php");
            if (is_readable ($helper_file)) {require_once $helper_file;}
        }

        // Load base controllers (public/private/api) - manual require, not via autoloader
        // $coreDir = interlace (Registry::get ('system'), 'Core');
        // foreach (['Public', 'Private', 'Api'] as $base) {
        //     $baseFile = interlace ($coreDir, "{$base}.core.php");
        //     if (is_readable ($baseFile)) {require_once $baseFile;}
        // }

        // Activation/Deactivation - use plugin_key from Registry
        $plugin_key = Registry::get ('plugin_key');
        register_activation_hook ($plugin_key, [$this, 'activate']);
        register_deactivation_hook ($plugin_key, [$this, 'deactivate']);
        register_uninstall_hook ($plugin_key, [__CLASS__, 'uninstall']);

        // Core routing interception - parse_request runs early
        add_action ('parse_request', [$this, 'handleRouting'], 10, 2);

        // Flush rewrite rules on permalink save
        add_action ('flush_rewrite_rules', [$this, 'flushRewriteRules']);

        // Admin notices
        add_action ('admin_notices', [$this, 'adminNotices']);
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
        // Flush rewrite rules
        flush_rewrite_rules ();
    }

    public function flushRewriteRules (): void
    {
        flush_rewrite_rules ();
    }

    /**
     * Main routing handler - intercepts requests before WordPress
     *
     * @param \WP $wp WP instance
     * @param array $extra_query_vars Extra query vars
     * @return void
     */
    public function handleRouting (\WP $wp, array $extra_query_vars = []): void
    {
        // Get request path
        $path = $this->getRequestPath ($wp);

        // Skip admin, ajax, rest, and static files
        if ($this->shouldSkipRouting ($path, $wp)) {return;}

        // Parse segments
        $segments = is ($path, 'home') ? [] : explode ('/', $path);

        // Let Router handle the dispatch - only exit WP if it actually matched
        $handled = Router::handle ($segments);

        if ($handled) {
            $wp->query_vars['radykfw_handled'] = true;
            add_action ('template_redirect', function() {exit;}, 1);
        }
    }

    private function getRequestPath (\WP $wp): string
    {
        $request = isset ($wp->request) ? $wp->request : '';
        $path = trim ($request, '/');

        if (empty ($path)) {$path = 'home';}

        return $path;
    }

    private function shouldSkipRouting (string $path, \WP $wp): bool
    {
        // Skip admin
        if (is_admin() && !wp_doing_ajax()) {return true;}

        // Skip AJAX
        if (wp_doing_ajax()) {return true;}

        // Skip REST API
        if (str_starts_with ($path, 'wp-json/') || str_starts_with ($path, 'rest/')) {return true;}

        // Skip login/wp-admin related
        if (in_array ($path, ['wp-login.php', 'wp-signup.php', 'wp-activate.php'], true)) {return true;}

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
