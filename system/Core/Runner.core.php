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

    /**
     * Admin notice registry (shared across all notice emitters).
     *
     * @var array<int, array{message: string, in_page: string[]|string|null, type: string}>
     */
    private static array $admin_notices = [];

    /** @var array<int, array{file: string, kind: string}> Helper files skipped because they declare a type. */
    private array $helper_skips = [];

    private function __construct ()
    {
        $this->initHooks ();
    }

    public static function instance (): self
    {
        if (is (self::$instance, null)) {self::$instance = new self();}
        return self::$instance;
    }

    /**
     * Register an admin notice. Emitted by any module / function via the
     * static API; rendered centrally by `adminNotices()`.
     *
     * @param string              $message The notice text (HTML-escaped by the renderer).
     * @param string[]|string|null $in_page Screen IDs where the notice shows. `null` = all pages.
     * @param string              $type    CSS class suffix: 'error' | 'warning' | 'info'.
     */
    public static function addAdminNotice (string $message, string|array|null $in_page = null, string $type = 'error'): void
    {
        self::$admin_notices[] = ['message' => $message, 'in_page' => $in_page, 'type' => $type];
    }

    /** @return array<int, array{message: string, in_page: string[]|string|null, type: string}> Read-only copy of the registry. */
    public static function getAdminNotices (): array
    {
        return self::$admin_notices;
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
                $this->helper_skips[] = ['file' => "{$stem}.helper.php", 'kind' => $kind];
                self::addAdminNotice (
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
            add_action ('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
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
        $home_url = home_url ('/');
        $requested = rtrim ($requested_url, '/');

        // Static claimed routes come from config (routing.aliases keys) — no hardcoded list.
        // Pattern keys (containing '{') are skipped: they are not literal URLs.
        foreach (array_keys (get_config ('routing.aliases', [])) as $key)
        {
            $space = strpos ($key, ' ');
            if ($space === false) {continue;}

            $path = trim (substr ($key, $space), ' /');
            if ($path === '' || str_contains ($path, '{')) {continue;}

            // Match both with and without trailing slash
            if ($requested === $home_url . $path) {
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
     * @param \WP $wp Wordpress instance
     * @return void
     */
    public function handleRouting (\WP $wp): void
    {
        // Get request path (from WP, already rewritten)
        $path = $this->getRequestPath ($wp);

        // Skip admin, ajax, rest, and static files
        if ($this->shouldSkipRouting ($path, $wp)) {return;}

        // Aliases are explicit route claims and take priority in BOTH modes.
        $handled = $this->tryAliases ();

        if (!$handled && get_config ('routing.native', true)) {
            // WP-content-first: if WordPress actually owns this path, let it win.
            if ($this->wpHasRealContent ($path)) {return;}
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
     * Decide whether WordPress actually owns the given path (a real page or
     * post exists for it), BEFORE WordPress runs its main query.
     *
     * Replaces the old query-var heuristic which was always true for any
     * path shape under pretty permalinks. Uses object-cache-friendly core
     * lookups so the cost is minimal.
     *
     * @param string $path Normalised request path (no leading slash)
     * @return bool True if WordPress has a real page/post at this path
     */
    private function wpHasRealContent (string $path): bool
    {
        $cleaned = trim ($path, '/');
        if ($cleaned === '' || str_contains ($cleaned, '.')) {return false;}

        // Page at this slug (post_type 'page')
        if (\get_page_by_path ($cleaned, OBJECT, 'page') !== null) {return true;}

        // Post at this slug (object-cache friendly via get_posts by name)
        $found = \get_posts ([
            'name' => $cleaned,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        if (!empty ($found)) {return true;}

        return false;
    }

    /**
     * Try to dispatch via route aliases.
     *
     * @return bool True if alias matched and dispatched
     */
    private function tryAliases (): bool
    {
        $aliases = get_config ('routing.aliases', []);
        $nsmod = Registry::get ('nsmod'); // trailing backslash: "Modules\"
        $httpMethod = strtoupper (catch_server ('request.method', 'get'));
    
        // Use original request URI for alias matching (before WP rewrite)
        $requestUri = catch_server ('request.uri', '');
        $requestPath = parse_url ($requestUri, PHP_URL_PATH);
        $requestPath = trim ($requestPath, '/');
    
        // Strip plugin base path (e.g. /radykfwtest/login → login)
        $pluginBase = trim (parse_url (home_url ('/'), PHP_URL_PATH), '/');
        if (!empty ($pluginBase) && str_starts_with ($requestPath, $pluginBase . '/')) {
            $requestPath = substr ($requestPath, strlen ($pluginBase) + 1);
        }

        $aliasKey = "{$httpMethod} /" . $requestPath;

        // 1. Exact match — highest priority (no placeholders)
        if (isset ($aliases[$aliasKey])) {
            return $this->dispatchAlias ($aliases[$aliasKey], $nsmod, []);
        }

        // 2. Pattern match — placeholders {seg} / {seg:int}, equal segment count
        $prefix = "{$httpMethod} /";
        foreach ($aliases as $key => $target) {
            if (!str_starts_with ($key, $prefix)) {continue;}
            $pattern = substr ($key, strlen ($prefix));
            if (!str_contains ($pattern, '{')) {continue;}
            $params = [];
            if ($this->matchAliasPattern ($pattern, $requestPath, $params)
                && $this->dispatchAlias ($target, $nsmod, $params)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an alias pattern with placeholders against a request path.
     *
     * Segment {name}     = string, must match [A-Za-z0-9_-]+ (blocks traversal)
     * Segment {name:int} = digits only; value still passed as string
     * Literal segments must match exactly; segment counts must be equal.
     *
     * @param string $pattern Alias path after the METHOD prefix (e.g. 'door/{id:int}')
     * @param string $path    Normalised request path (e.g. 'door/42')
     * @param array  $params  Captured values (always strings), by reference
     * @return bool True if pattern matches
     */
    private function matchAliasPattern (string $pattern, string $path, array &$params): bool
    {
        $patternSegs = explode ('/', $pattern);
        $pathSegs    = explode ('/', $path);

        if (count ($patternSegs) !== count ($pathSegs)) {return false;}

        $params = [];
        foreach ($patternSegs as $i => $pSeg) {
            if (preg_match ('/^\{(\w+)(?::(\w+))?\}$/', $pSeg, $m)) {
                $type = $m[2] ?? 'string';
                if ($type === 'int') {
                    if (preg_match ('/^\d+$/', $pathSegs[$i]) !== 1) {return false;}
                } else {
                    if (preg_match ('/^[A-Za-z0-9_-]+$/', $pathSegs[$i]) !== 1) {return false;}
                }
                $params[] = $pathSegs[$i];
            } elseif ($pSeg !== $pathSegs[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse an alias target and dispatch it with captured params.
     *
     * @param string $target Alias value: 'Module\Controller::method' or 'Module::method'
     * @param string $nsmod  Namespace prefix from Registry (trailing backslash)
     * @param array  $params Params to pass to the method
     * @return bool True if dispatched successfully
     */
    private function dispatchAlias (string $target, string $nsmod, array $params): bool
    {
        if (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)\\\\([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $target, $m)) {
            // Explicit: Module\Controller::method
            $class = "{$nsmod}{$m[1]}\\Controllers\\{$m[2]}";
            Router::setCurrentModule ($m[1]);
            if (Router::tryDispatch ($class, $m[3], $params)) {return true;}
        } elseif (preg_match ('/^([A-Za-z_][A-Za-z0-9_]*)::(\w+)$/', $target, $m)) {
            // Shorthand: Module::method (controller = module)
            $class = "{$nsmod}{$m[1]}\\Controllers\\{$m[1]}";
            Router::setCurrentModule ($m[1]);
            if (Router::tryDispatch ($class, $m[2], $params)) {return true;}
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

    private function shouldSkipRouting (string $path, \WP $wp): bool
    {
        // Skip admin
        if (is_admin () && !wp_doing_ajax ()) {return true;}

        // Skip AJAX
        if (wp_doing_ajax ()) {return true;}

        // Skip REST API
        if (str_starts_with ($path, 'wp-json/') || str_starts_with ($path, 'rest/')) {return true;}

        // Skip login/wp-admin related + infra paths (from config, tanpa .php)
        if (in_array ($path, get_config ('routing.skip_paths', []), true)) {return true;}

        // Skip static files (from config)
        $skipExt = strtolower (pathinfo ($path, PATHINFO_EXTENSION));
        if ($skipExt !== '' && in_array ($skipExt, get_config ('routing.skip_extensions', []), true)) {return true;}

        // Skip if already handled by RadykFW
        if (isset ($wp->query_vars['radykfw_handled'])) {return true;}

        return false;
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

    /**
     * Gate a notice to specific admin pages. Returns true when the current
     * screen matches one of $pages; null means "always show".
     *
     * @param string[]|string|null $pages Screen IDs (e.g. ['dashboard','plugins']) or a single ID.
     */
    private function screenMatches (string|array|null $pages): bool
    {
        if ($pages === null) {return true;}
        if (! function_exists ('get_current_screen')) {return true;}

        $current = get_current_screen ();
        $current_id = $current?->id ?? '';

        $list = is_array ($pages) ? $pages : [$pages];
        return in_array ($current_id, $list, true);
    }

    public function adminNotices (): void
    {
        // Register PHP version notice (global — in_page = null)
        if (version_compare (PHP_VERSION, '8.1', '<'))
        {
            self::addAdminNotice (
                'Requires PHP 8.1 or higher. Current: ' . PHP_VERSION,
                null,
                'error'
            );
        }

        // Register WP version notice (global — in_page = null)
        if (version_compare ((string) Registry::get ('wp_version'), '6.0', '<'))
        {
            self::addAdminNotice (
                'Recommended WordPress 6.0+. Current: ' . Registry::get ('wp_version'),
                null,
                'warning'
            );
        }

        // Render all registered notices, gated per notice via in_page.
        foreach (self::$admin_notices as $notice)
        {
            if (! $this->screenMatches ($notice ['in_page'])) {continue;}

            $type = in_array ($notice ['type'], ['error', 'warning', 'info'], true) ? $notice ['type'] : 'error';
            echo '<div class="notice notice-' . $type . '"><p><strong>RadykFW:</strong> '
                . wp_kses_post ($notice ['message'])
                . '</p></div>';
        }
    }

    /**
     * Register RADYK Labs admin menus (top-level + sub-menus).
     * First-owner logic: if another product already registered the top-level
     * menu, we only add our sub-menus.
     */
    public function registerAdminMenus (): void
    {
        // Register top-level menu (first-owner wins)
        \System\Core\AdminUI::registerMenu ([
            'slug'       => 'radyk',
            'title'      => 'RADYK',
            'capability' => 'manage_options',
            'icon'       => 'dashicons-superhero',
            'position'   => '59.1',
            'callback'   => [$this, 'renderDashboardPage'],
                // 'separator_after' => '59.5',
        ]);

        \System\Core\AdminUI::addMenuSeparator ('59.9');

        // Register our sub-menu: Modules (Overview + Routing)
        \System\Core\AdminUI::registerSubmenu ('radyk', [
            'slug'       => 'radyk-modules',
            'title'      => 'Modules',
            'capability' => 'manage_options',
            'callback'   => [$this, 'renderModulesPage'],
            'position'   => 10,
        ]);

        // Register nonce action for Modules page
        \System\Core\AdminUI::registerNonceAction ('radyk_modules_action');

        // Register nonce action for Modules page
        \System\Core\AdminUI::registerNonceAction ('radyk_modules_action');

        // Actually register with WordPress (call once after all registrations)
        \System\Core\AdminUI::register ();
    }

    /**
         * Render the Modules admin page (Overview + Routing).
         */
    public function renderModulesPage (): void
    {
            // Verify capability
        if (! current_user_can ('manage_options')) {wp_die ('Insufficient permissions');}

        // Verify nonce only on POST (form submissions)
        if ($_SERVER ['REQUEST_METHOD'] === 'POST')
        {
            if (! \System\Core\AdminUI::verifyNonce ('radyk_modules_action')) {wp_die ('Invalid nonce');}
        }

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);

        // Get active modules from config
        $modules = get_config ('modules.auto_discover', false);

        // Render page
        echo '<div class="wrap radyk-wrap">';
        echo '<h1>RADYK Labs — Modules</h1>';

        // Overview section
        echo '<div class="radyk-section">';
        echo '<h2>Overview</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>Routing Mode</td><td>' . ($native ? 'WordPress First (native)' : 'HMVC First') . '</td></tr>';
        echo '<tr><td>Auto-discover Modules</td><td>' . ($modules ? 'Enabled' : 'Disabled (alias-only)') . '</td></tr>';
        echo '<tr><td>Total Aliases</td><td>' . count ($aliases) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        // Routing section
        echo '<div class="radyk-section" style="margin-top:20px;">';
        echo '<h2>Routing Aliases</h2>';
        if (empty ($aliases))
        {
            echo '<p>No aliases configured.</p>';
        }
        else
        {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Method / Path</th><th>Target</th></tr></thead><tbody>';
            foreach ($aliases as $key => $target)
            {
                echo '<tr><td><code>' . esc_html ($key) . '</code></td><td><code>' . esc_html ($target) . '</code></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        // Debug log section
        echo '<div class="radyk-section" style="margin-top:20px;">';
        echo '<h2>Debug Log</h2>';
        echo '<p>Admin notices registry: ' . count (\System\Core\Runner::getAdminNotices ()) . ' notice(s) registered.</p>';
        echo '<p>Helper skips: ' . count ($this->helper_skips) . ' file(s) skipped.</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render the Dashboard admin page (top-level RADYK menu).
     */
    public function renderDashboardPage (): void
    {
        error_log ('=== renderDashboardPage called ===');
        // Verify capability
        if (! current_user_can ('manage_options')) {wp_die ('Insufficient permissions');}

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);
        $modules = get_config ('modules.auto_discover', false);

        // Render page
        echo '<div class="wrap radyk-wrap">';
        echo '<h1>RADYK Labs — Dashboard</h1>';

        // Quick stats
        echo '<div class="radyk-section">';
        echo '<h2>Quick Stats</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>Routing Mode</td><td>' . ($native ? 'WordPress First (native)' : 'HMVC First') . '</td></tr>';
        echo '<tr><td>Auto-discover Modules</td><td>' . ($modules ? 'Enabled' : 'Disabled (alias-only)') . '</td></tr>';
        echo '<tr><td>Total Aliases</td><td>' . count ($aliases) . '</td></tr>';
        echo '<tr><td>Admin Notices</td><td>' . count (\System\Core\Runner::getAdminNotices ()) . '</td></tr>';
        echo '<tr><td>Helper Skips</td><td>' . count ($this->helper_skips) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        // Quick links
        echo '<div class="radyk-section" style="margin-top:20px;">';
        echo '<h2>Quick Links</h2>';
        echo '<p>';
        echo '<a href="' . esc_url (admin_url ('admin.php?page=radyk-modules')) . '" class="radyk-btn">Modules</a> ';
        echo '<a href="' . esc_url (admin_url ('options-general.php')) . '" class="radyk-btn secondary">Settings</a> ';
        echo '<a href="' . esc_url (admin_url ('plugins.php')) . '" class="radyk-btn secondary">Plugins</a>';
        echo '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Enqueue admin assets for RADYK Labs pages.
     * Only loads on our own admin pages (slug starts with 'radyk').
     */
    public function enqueueAdminAssets (): void
    {
        $screen = function_exists ('get_current_screen') ? get_current_screen () : null;
        $slug   = $screen?->id ?? '';

        // Only enqueue on our pages
        if (! str_starts_with ($slug, 'radyk')) {return;}

        // Shared CSS
        wp_enqueue_style ('radyk-admin', plugin_dir_url (keyfile) . 'assets/css/admin.css', [], '1.0.0');

        // Shared JS
        wp_enqueue_script ('radyk-admin', plugin_dir_url (keyfile) . 'assets/js/admin.js', ['jquery'], '1.0.0', true);
    }
}
