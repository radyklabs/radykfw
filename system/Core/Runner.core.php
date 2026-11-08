<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;
use System\Core\Router;
use System\Services\AdminUI;
use System\Services\Pages;
use System\Services\Assets;

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
        if (is (self::$instance, null)) {self::$instance = new self();}
        return self::$instance;
    }

    private function initHooks (): void
    {
        // Load global helper functions - file list from Config/helper.conf.php
        foreach (get_config ('helper') as $stem)
        {
            $helper_file = interlace (Registry::get ('helpers'), "{$stem}.helper.php");
            if (! is_readable ($helper_file)) {continue;}

            // Helpers are function-only files: a declared type would clash with
            // the autoloader / already-loaded classes, so skip and report it.
            $kind = self::declaredType ($helper_file);
            if ($kind !== null)
            {
                AdminUI::recordHelperSkip ("{$stem}.helper.php", $kind);
                AdminUI::addAdminNotice (
                    'Helper file <code>' . "{$stem}.helper.php" . '</code> was not loaded because it contains a ' . $kind . ' declaration. Helpers must only declare functions.',
                    ['dashboard', 'plugins'],
                    'error'
                );
                continue;
            }

            require_once $helper_file;
        }

        // Activation/Deactivation - use plugin_key from Registry
        $plugin_key = Registry::get ('plugin_key');
        register_activation_hook ($plugin_key, [$this, 'activate']);
        register_deactivation_hook ($plugin_key, [$this, 'deactivate']);
        register_uninstall_hook ($plugin_key, [__CLASS__, 'uninstall']);

        // Core routing interception - parse_request runs early
        if (function_exists ('add_action'))
        {
            add_action ('parse_request', [$this, 'handleRouting'], 10, 2);
            add_action ('flush_rewrite_rules', [$this, 'flushRewriteRules']);
            add_action ('admin_notices', [$this, 'adminNotices']);
            add_action ('admin_menu', [$this, 'registerAdminMenus']);
            add_action ('admin_enqueue_scripts', [Assets::class, 'enqueueAdminAssets']);
        }

        if (function_exists ('add_filter'))
        {
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
        return Router::filterCanonicalRedirect ($redirect_url, $requested_url);
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
     * @param \WP $wp Wordpress instance
     * @return void
     */
    public function handleRouting (\WP $wp): void
    {
        // Get request path (from WP, already rewritten)
        $path = Router::getRequestPath ($wp);

        // Skip admin, ajax, rest, and static files
        if (Router::shouldSkipRouting ($path, $wp)) {return;}

        // Aliases are explicit route claims and take priority in BOTH modes.
        $handled = Router::tryAliases ();

        if (!$handled && get_config ('routing.native', true)) {
            // WP-content-first: if WordPress actually owns this path, let it win.
            if (Router::wpHasRealContent ($path)) {return;}
        }

        // Auto-discovery (HMVC-first always; WP-first only when WP has no content)
        if (!$handled) {
            $handled = Router::handle (explode ('/', $path));
        }

        if ($handled) {
            $wp->query_vars['radykfw_handled'] = true;
            if (defined ('WP_DEBUG') && WP_DEBUG) {
                error_log ("RadykFW: Route handled, exiting");
            }
            exit;
        }

        // No claim - let WordPress handle it (content, or 404)
            }

            public function adminNotices (): void
    {
        AdminUI::adminNotices ();
    }

    /**
     * Register RADYK Labs admin menus (top-level + sub-menus).
     * First-owner logic: if another product already registered the top-level
     * menu, we only add our sub-menus.
     */
    public function registerAdminMenus (): void
    {
        // Register top-level menu (first-owner wins)
        AdminUI::registerMenu ([
            'slug'       => 'radyk',
            'title'      => 'RADYK',
            'capability' => 'manage_options',
            'icon'       => 'dashicons-superhero',
            'position'   => '59.1',
            'callback'   => [Pages::class, 'renderDashboardPage'],
            // 'separator_after' => '59.5',
        ]);

        AdminUI::addMenuSeparator ('59.9');

        // Register our sub-menu: Modules (Overview + Routing)
        AdminUI::registerSubmenu ('radyk', [
            'slug'       => 'radyk-modules',
            'title'      => 'Modules',
            'capability' => 'manage_options',
            'callback'   => [Pages::class, 'renderModulesPage'],
            'position'   => 10,
        ]);

        // Register nonce action for Modules page
        AdminUI::registerNonceAction ('radyk_modules_action');

        // Actually register with WordPress (call once after all registrations)
        AdminUI::register ();
    }

    /**
     * Enqueue admin assets for RADYK Labs pages.
     * Only loads on our own admin pages (slug starts with 'radyk').
     */
    public function enqueueAdminAssets (): void
    {
        Assets::enqueueAdminAssets ();
    }

    /**
     * Detect a type declaration (class/interface/trait/enum) in a file.
     * `Foo::class` constant references are ignored.
     *
     * @param string $file Absolute path of the file to scan.
     * @return string|null Kind name when a declaration is found, null otherwise.
     */
    private static function declaredType (string $file): ?string
    {
        $kinds = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait'];
        if (defined ('T_ENUM')) {$kinds [constant ('T_ENUM')] = 'enum';}

        $prev = null;

        foreach (token_get_all ((string) file_get_contents ($file)) as $token)
        {
            if (! is_array ($token)) {continue;} // punctuation: keep last keyword

            $id = $token [0];

            if (in_array ($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {continue;}

            // `Foo::class` is a constant, not a declaration.
            if (isset ($kinds [$id]) && $prev !== T_DOUBLE_COLON) {return $kinds [$id];}

            $prev = $id;
        }

        return null;
    }
}