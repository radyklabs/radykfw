<?php declare (strict_types=1);

namespace System\Core;

use System\Core\Registry;
use System\Core\Router;
use System\Services\AdminUI;
use System\Services\Pages;
use System\Services\Assets;
use System\Services\I18n;
use System\Services\Settings;

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
        I18n::register ();
        Settings::registerHooks ();

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
                add_action ('init', static function () use ($stem, $kind): void {
                    AdminUI::addAdminNotice (
                        sprintf (
                            __ ('Helper file <code>%1$s</code> was not loaded because it contains a %2$s declaration. Helpers must only declare functions.', 'radykfw'),
                            esc_html ("{$stem}.helper.php"),
                            esc_html ($kind)
                        ),
                        ['dashboard', 'plugins'],
                        'error'
                    );
                }, 1);
                continue;
            }

            require_once $helper_file;
        }

        // Register nonce actions from actions.conf.php (early, not tied to admin_menu)
        $catalog = \System\Services\ModuleCatalog::discover ();
        $nonce_actions = array_merge (get_config ('actions.nonce_actions', []), $catalog['actions']);
        \System\Services\ModuleCli::register ();
        foreach ($nonce_actions as $action) {
            \System\Services\Actions::registerNonceAction ($action);
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
            add_action ('admin_notices', [AdminUI::class, 'adminNotices']);
            add_action ('admin_enqueue_scripts', [Assets::class, 'enqueueAdminAssets']);
            add_action ('admin_enqueue_scripts', [Assets::class, 'enqueueRadykAdminAssets']);
            add_action ('wp_enqueue_scripts', [Assets::class, 'enqueueFrontendAssets']);
        }

        AdminUI::registerHooks ();

        if (function_exists ('add_filter'))
        {
            add_filter ('redirect_canonical', [Router::class, 'filterCanonicalRedirect'], 10, 2);
        }
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

        if (!$handled && Settings::getConfigValue ('routing.native', get_config ('routing.native', true))) {
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
