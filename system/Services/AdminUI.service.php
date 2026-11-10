<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * AdminUI — Shared admin UI registry for RADYK products.
 *
 * Top-level menus are owned by the first product registering each slug.
 * Other products can reuse an existing menu and add their sub-menus.
 *
 * Usage (from any product, e.g. RadykFW, RadykThemes):
 *   AdminUI::registerMenu ([
 *       'slug'       => 'radyk',
 *       'title'      => 'RADYK',
 *       'capability' => 'manage_options',
 *       'icon'       => 'dashicons-admin-generic',
 *       'position'   => 59,
 *       'separator_after' => '59.5',
 *   ]);
 *   AdminUI::registerSubmenu ('radyk', [
 *       'slug'       => 'radyk-modules',
 *       'title'      => 'Modules',
 *       'capability' => 'manage_options',
 *       'callback'   => [MyModuleController::class, 'render'],
 *       'position'   => 10,
 *   ]);
 *   AdminUI::registerAssets ('radyk', [
 *       'css' => ['radyk-admin' => 'assets/css/admin.css'],
 *       'js'  => ['radyk-admin' => 'assets/js/admin.js'],
 *   ]);
 */
final class AdminUI
{
    /** @var array<string, array<string, mixed>> Top-level menus keyed by slug. */
    private static array $top_menus = [];

    /** @var array<int, array<string, mixed>> Sub-menus keyed by parent slug. */
    private static array $sub_menus = [];

    /** @var array<string, array<string, string>> Assets keyed by parent slug. */
    private static array $assets = [];

    /** @var bool Whether any top-level menu has been registered. */
    private static bool $top_registered = false;

    private static bool $hooks_registered = false;

    private static bool $menus_registered = false;


    /** @var array<int, array{message: string, in_page: string[]|string|null, type: string}> Admin notices registry. */
    private static array $admin_notices = [];

    /** @var array<int, array{file: string, kind: string}> Helper files skipped because they declare a type. */
    private static array $helper_skips = [];

    private function __construct () {}

    /**
     * Register (or claim) a top-level menu.
     * The first caller for each slug wins.
     *
     * @param array<string, mixed> $config
     *   - slug (string, required): menu slug, e.g. 'radyk'
     *   - title (string, required): display title, e.g. 'RADYK'
     *   - capability (string, default 'manage_options')
     *   - icon (string, default 'dashicons-admin-generic')
     *   - position (int|float|string, default 59)
     *   - callback (callable, optional): render callback for top-level page
     *   - separator_after (int|float|string, optional): position for separator after this menu
     * @return bool True if this call became the owner, false if menu already owned.
     */
    public static function registerMenu (array $config): bool
    {
        $slug = $config ['slug'] ?? '';
        if (! self::isValidMenuSlug ($slug) || isset (self::$top_menus [$slug])) {return false;}

        $menu = [
            'slug'           => $slug,
            'title'          => $config ['title'] ?? 'RADYK',
            'capability'     => $config ['capability'] ?? 'manage_options',
            'icon'           => $config ['icon'] ?? 'dashicons-admin-generic',
            'position'       => $config ['position'] ?? 59,
            'callback'       => $config ['callback'] ?? null,
            'submenu'        => $config ['submenu'] ?? true,
            'separator_after'=> $config ['separator_after'] ?? null,
        ];
        if (! self::validateMenuEntry ($menu, 'AdminUI::registerMenu')) {return false;}

        self::$top_menus [$slug] = $menu;
        self::$top_registered = true;
        return true;
    }

    /**
     * Register a sub-menu under a registered top-level menu.
     *
     * @param string $parent_slug Parent menu slug (must match registerMenu slug).
     * @param array<string, mixed> $config
     *   - slug (string, required): sub-menu slug, e.g. 'radyk-modules'
     *   - title (string, required): display title
     *   - capability (string, default 'manage_options')
     *   - callback (callable, optional): function to render the page
     *   - position (int|float|string, optional): menu position for ordering
     * @return bool True if registered, false if parent not found.
     */
    public static function registerSubmenu (string $parent_slug, array $config): bool
    {
        $slug = $config ['slug'] ?? '';
        if (! isset (self::$top_menus [$parent_slug]) || ! self::isValidMenuSlug ($slug)) {return false;}

        foreach (self::$sub_menus as $submenus)
        {
            foreach ($submenus as $submenu)
            {
                if ($submenu ['slug'] === $slug) {return false;}
            }
        }

        if (isset (self::$top_menus [$slug]) && $slug !== $parent_slug) {return false;}

        $submenu = [
            'slug'       => $slug,
            'title'      => $config ['title'] ?? '',
            'capability' => $config ['capability'] ?? 'manage_options',
            'callback'   => $config ['callback'] ?? null,
            'position'   => $config ['position'] ?? null,
        ];
        if (! self::validateMenuEntry ($submenu, 'AdminUI::registerSubmenu')) {return false;}

        self::$sub_menus [$parent_slug] [] = $submenu;
        return true;
    }

    /**
     * Register assets (CSS/JS) for admin pages under the top-level menu.
     *
     * @param string $parent_slug Parent menu slug.
     * @param array<string, array<string, string>> $assets
     *   - css: array of handle => path (relative to plugin root)
     *   - js:  array of handle => path
     */
    public static function registerAssets (string $parent_slug, array $assets): void
    {
        if (! isset (self::$top_menus [$parent_slug]))
        {
            return;
        }
        self::$assets [$parent_slug] = $assets;
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

    /** @return array<int, array{message: string, in_page: string[]|string|null, type: string}> Read-only copy of the notice registry. */
    public static function getAdminNotices (): array
    {
        return self::$admin_notices;
    }

    /**
     * Add a menu separator at a specific position.
     *
     * @param int|float|string $order Position in the menu (e.g., 59.5)
     * @param string $menu_title Optional title for the separator
     */
    public static function addMenuSeparator (int|float|string $order, string $menu_title = ''): void
    {
        global $menu;

        $key = (string) $order;
        if (isset ($menu [$key])) {return;}

        $menu [$key] = [
            $menu_title,              // Menu title
            'read',                   // Required user capability
            'separator-custom',       // Unique URL slug
            '',                       // Page title
            'wp-menu-separator'       // WordPress CSS class for lines
        ];
    }

    /**
     * Get the top-level menu config (read-only).
     */
    public static function getTopMenu (): array
    {
        return self::$top_menus === [] ? [] : reset (self::$top_menus);
    }

    /**
     * Get all top-level menu configs keyed by slug (read-only).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getTopMenus (): array
    {
        return self::$top_menus;
    }

    /**
     * Get sub-menus for a parent slug (read-only).
     */
    public static function getSubmenus (string $parent_slug): array
    {
        return self::$sub_menus [$parent_slug] ?? [];
    }

    /**
     * Get assets for a parent slug (read-only).
     */
    public static function getAssets (string $parent_slug): array
    {
        return self::$assets [$parent_slug] ?? [];
    }

    /**
     * Check if top-level menu is already registered (first-owner check).
     */
    public static function isTopMenuRegistered (): bool
    {
        return self::$top_registered;
    }

    /**
     * Get the owner slug (the slug of the product that registered first).
     */
    public static function getOwnerSlug (): string
    {
        return array_key_first (self::$top_menus) ?? '';
    }

    /**
     * Hook menu registration after products have contributed their menus.
     */
    public static function registerHooks (): void
    {
        if (self::$hooks_registered || ! function_exists ('add_action')) {return;}
        add_action ('admin_menu', [self::class, 'registerMenus'], PHP_INT_MAX);
        self::$hooks_registered = true;
    }

    /**
     * Load default and module menu configs, apply the public filter, then
     * register all collected menus with WordPress.
     */
    public static function registerMenus (): void
    {
        if (self::$menus_registered) {return;}

        $config = self::loadMenuConfig ();
        if ($config === null) {return;}

        $separator = $config ['separator'] ?? [];
        if (isset ($separator ['position']))
        {
            self::addMenuSeparator ($separator ['position']);
        }

        foreach ($config ['menus'] as $menu)
        {
            self::registerMenu ($menu);
        }

        foreach ($config ['submenus'] as $parent_slug => $submenus)
        {
            foreach ($submenus as $submenu)
            {
                if (! self::registerSubmenu ($parent_slug, $submenu))
                {
                    self::reportMenuConfigIssue (
                        'radykfw_admin_menu_config',
                        sprintf ('The submenu slug "%s" is already registered or its parent is unavailable.', $submenu ['slug'])
                    );
                }
            }
        }

        self::register ();
        self::$menus_registered = true;
    }

    /**
     * Register all claimed menus with WordPress.
     */
    public static function register (): void
    {
        if (! self::$top_registered) {return;}

        foreach (self::$top_menus as $slug => $menu)
        {
            if (isset ($menu ['separator_after']))
            {
                self::addMenuSeparator ($menu ['separator_after'], '');
            }

            add_menu_page (
                $menu ['title'],
                $menu ['title'],
                $menu ['capability'],
                $slug,
                $menu ['callback'] ?? null,
                $menu ['icon'] ?? 'dashicons-admin-generic',
                $menu ['position'] ?? null
            );

            $subs = self::$sub_menus [$slug] ?? [];
            if (! empty ($subs))
            {
                usort ($subs, static function ($a, $b) {
                    $pos_a = $a ['position'] ?? 999;
                    $pos_b = $b ['position'] ?? 999;
                    return $pos_a <=> $pos_b;
                });

                foreach ($subs as $sub)
                {
                    add_submenu_page (
                        $slug,
                        $sub ['title'],
                        $sub ['title'],
                        $sub ['capability'],
                        $sub ['slug'],
                        $sub ['callback'] ?? null,
                        $sub ['position'] ?? null
                    );
                }
            }

            if (! $menu ['submenu']) {remove_submenu_page ($slug, $slug);}
        }
    }

    /**
     * Load the system config, merge optional module configs in stable order,
     * and pass the result through the WordPress extension filter.
     *
     * @return array<string, mixed>|null
     */
    private static function loadMenuConfig (): ?array
    {
        $config = get_config ('menus', []);
        if (! is_array ($config) || ! self::validateMenuConfig ($config, 'system/Config/menus.conf.php'))
        {
            return null;
        }

        foreach (self::discoverModuleMenuConfigs () as $module => $contribution)
        {
            if (! is_array ($contribution))
            {
                self::reportMenuConfigIssue ("module {$module}", 'The file must return an array.');
                continue;
            }

            $unknown_keys = array_diff (array_keys ($contribution), ['menus', 'submenus']);
            if ($unknown_keys !== [])
            {
                self::reportMenuConfigIssue ("module {$module}", 'Only "menus" and "submenus" keys are supported.');
                continue;
            }

            $fragment = [
                'menus'    => $contribution ['menus'] ?? [],
                'submenus' => $contribution ['submenus'] ?? [],
            ];
            if (
                ! is_array ($fragment ['menus'])
                || ! array_is_list ($fragment ['menus'])
                || ! is_array ($fragment ['submenus'])
            )
            {
                self::reportMenuConfigIssue ("module {$module}", 'Expected a menus list and a submenus map.');
                continue;
            }

            $valid_submenus = true;
            foreach ($fragment ['submenus'] as $parent_slug => $submenus)
            {
                if (! is_string ($parent_slug) || ! is_array ($submenus) || ! array_is_list ($submenus))
                {
                    $valid_submenus = false;
                    break;
                }
            }
            if (! $valid_submenus)
            {
                self::reportMenuConfigIssue ("module {$module}", 'Each submenu group must be a list keyed by parent slug.');
                continue;
            }

            $candidate = self::mergeMenuConfig ($config, $fragment);
            if (! self::validateMenuConfig ($candidate, "module {$module}"))
            {
                continue;
            }

            $config = $candidate;
        }

        if (function_exists ('apply_filters'))
        {
            $filtered = apply_filters ('radykfw_admin_menu_config', $config);
            if (! is_array ($filtered))
            {
                self::reportMenuConfigIssue ('radykfw_admin_menu_config', 'The filter must return an array.');
                return null;
            }
            $config = $filtered;
        }

        return self::validateMenuConfig ($config, 'radykfw_admin_menu_config') ? $config : null;
    }

    /**
     * Discover module menu config files using the stable module-name order.
     *
     * @return array<string, mixed>
     */
    private static function discoverModuleMenuConfigs (): array
    {
        $catalog = Module::discover ();
        foreach ($catalog['errors'] as $error) {self::reportMenuConfigIssue ('module discovery', $error);}
        return $catalog['menus'];
    }

    /**
     * Merge menu lists and submenu lists while preserving their declaration order.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $contribution
     * @return array<string, mixed>
     */
    private static function mergeMenuConfig (array $base, array $contribution): array
    {
        $base ['menus'] = array_merge ($base ['menus'] ?? [], $contribution ['menus'] ?? []);
        foreach ($contribution ['submenus'] ?? [] as $parent_slug => $submenus)
        {
            $base ['submenus'] [$parent_slug] = array_merge (
                $base ['submenus'] [$parent_slug] ?? [],
                $submenus
            );
        }
        return $base;
    }

    /**
     * Validate menu shape, slugs, capabilities and slug uniqueness.
     *
     * @param array<string, mixed> $config
     */
    private static function validateMenuConfig (array $config, string $source): bool
    {
        if (
            ! isset ($config ['menus'], $config ['submenus'])
            || ! is_array ($config ['menus'])
            || ! array_is_list ($config ['menus'])
            || ! is_array ($config ['submenus'])
        )
        {
            self::reportMenuConfigIssue ($source, 'Expected a menus list and a submenus map.');
            return false;
        }

        $top_slugs = [];
        foreach ($config ['menus'] as $menu)
        {
            if (! is_array ($menu) || ! self::validateMenuEntry ($menu, $source))
            {
                return false;
            }

            $slug = $menu ['slug'];
            if (isset ($top_slugs [$slug]))
            {
                self::reportMenuConfigIssue ($source, sprintf ('Duplicate top-level slug "%s".', $slug));
                return false;
            }
            $top_slugs [$slug] = true;
        }

        $submenu_slugs = [];
        foreach ($config ['submenus'] as $parent_slug => $submenus)
        {
            if (
                ! is_string ($parent_slug)
                || ! self::isValidMenuSlug ($parent_slug)
                || ! is_array ($submenus)
                || ! array_is_list ($submenus)
            )
            {
                self::reportMenuConfigIssue ($source, 'Each submenu group must use a valid parent slug and a list of entries.');
                return false;
            }

            if (! isset ($top_slugs [$parent_slug]) && ! isset (self::$top_menus [$parent_slug]))
            {
                self::reportMenuConfigIssue ($source, sprintf ('The submenu parent "%s" is not registered.', $parent_slug));
                return false;
            }

            foreach ($submenus as $submenu)
            {
                if (! is_array ($submenu) || ! self::validateMenuEntry ($submenu, $source))
                {
                    return false;
                }

                $slug = $submenu ['slug'];
                if (isset ($submenu_slugs [$slug]))
                {
                    self::reportMenuConfigIssue ($source, sprintf ('Duplicate submenu slug "%s".', $slug));
                    return false;
                }
                if (isset ($top_slugs [$slug]) && $slug !== $parent_slug)
                {
                    self::reportMenuConfigIssue ($source, sprintf ('The submenu slug "%s" conflicts with a top-level menu.', $slug));
                    return false;
                }
                if (isset (self::$top_menus [$slug]) && $slug !== $parent_slug)
                {
                    self::reportMenuConfigIssue ($source, sprintf ('The submenu slug "%s" conflicts with an existing top-level menu.', $slug));
                    return false;
                }
                $submenu_slugs [$slug] = true;
            }
        }

        if (isset ($config ['separator']))
        {
            if (
                ! is_array ($config ['separator'])
                || (isset ($config ['separator'] ['position']) && ! is_numeric ($config ['separator'] ['position']))
            )
            {
                self::reportMenuConfigIssue ($source, 'The separator position must be numeric.');
                return false;
            }
        }

        return true;
    }

    /**
     * Validate one menu or submenu entry.
     *
     * @param array<string, mixed> $entry
     */
    private static function validateMenuEntry (array $entry, string $source): bool
    {
        $slug = $entry ['slug'] ?? null;
        $title = $entry ['title'] ?? null;
        $capability = $entry ['capability'] ?? 'manage_options';
        $position = $entry ['position'] ?? null;

        if (
            ! self::isValidMenuSlug ($slug)
            || ! is_string ($title)
            || trim ($title) === ''
            || ! is_string ($capability)
            || ! preg_match ('/^[A-Za-z0-9_-]+$/D', $capability)
            || ($position !== null && ! is_numeric ($position))
            || (isset ($entry ['submenu']) && ! is_bool ($entry ['submenu']))
            || (isset ($entry ['icon']) && ! is_string ($entry ['icon']))
            || (isset ($entry ['separator_after']) && ! is_numeric ($entry ['separator_after']))
            || (isset ($entry ['callback']) && $entry ['callback'] !== null && ! is_callable ($entry ['callback']))
        )
        {
            self::reportMenuConfigIssue ($source, 'A menu entry has an invalid slug, title, capability, callback, or position.');
            return false;
        }

        return true;
    }

    private static function isValidMenuSlug (mixed $slug): bool
    {
        if (
            ! is_string ($slug)
            || trim ($slug) !== $slug
        )
        {
            return false;
        }

        [$path, $query] = array_pad (explode ('?', $slug, 2), 2, null);
        if ($path === '' || preg_match ('/^[A-Za-z0-9_.\/-]+$/D', $path) !== 1) {return false;}
        if (str_starts_with ($path, '/') || str_contains ($path, '//')) {return false;}
        foreach (explode ('/', $path) as $segment)
        {
            if (in_array ($segment, ['.', '..'], true)) {return false;}
        }
        if (
            $query !== null
            && preg_match ('/^[A-Za-z0-9_-]+=[A-Za-z0-9_.-]+(?:&[A-Za-z0-9_-]+=[A-Za-z0-9_.-]+)*$/D', $query) !== 1
        )
        {
            return false;
        }

        return true;
    }

    private static function reportMenuConfigIssue (string $source, string $message): void
    {
        self::addAdminNotice (
            sprintf (
                __ ('Admin menu config from %1$s was ignored: %2$s', 'radykfw'),
                esc_html ($source),
                esc_html ($message)
            ),
            null,
            'error'
        );
    }

    /**
     * Gate a notice to specific admin pages. Returns true when the current
     * screen matches one of $pages; null means "always show".
     *
     * @param string[]|string|null $pages Screen IDs (e.g. ['dashboard','plugins']) or a single ID.
     */
    private static function screenMatches (string|array|null $pages): bool
    {
        if ($pages === null) {return true;}
        if (! function_exists ('get_current_screen')) {return true;}

        $current = get_current_screen ();
        $current_id = $current?->id ?? '';

        $list = is_array ($pages) ? $pages : [$pages];
        return in_array ($current_id, $list, true);
    }

    /**
     * Render all registered admin notices, gated per notice via in_page.
     */
    public static function adminNotices (): void
    {
        // Register PHP version notice (global — in_page = null)
        if (version_compare (PHP_VERSION, '8.1', '<'))
        {
            self::addAdminNotice (
                sprintf (__ ('Requires PHP 8.1 or higher. Current: %s', 'radykfw'), PHP_VERSION),
                null,
                'error'
            );
        }

        // Register WP version notice (global — in_page = null)
        if (version_compare ((string) \System\Core\Registry::get ('wp_version'), '6.0', '<'))
        {
            self::addAdminNotice (
                sprintf (
                    __ ('Recommended WordPress 6.0+. Current: %s', 'radykfw'),
                    \System\Core\Registry::get ('wp_version')
                ),
                null,
                'warning'
            );
        }

        // Render all registered notices, gated per notice via in_page.
        foreach (self::$admin_notices as $notice)
        {
            if (! self::screenMatches ($notice ['in_page'])) {continue;}

            $type = in_array ($notice ['type'], ['error', 'warning', 'info'], true) ? $notice ['type'] : 'error';
            echo '<div class="notice notice-' . $type . '"><p><strong>RadykFW:</strong> '
                . wp_kses_post ($notice ['message'])
                . '</p></div>';
        }
    }

    /**
     * Record a helper file that was skipped because it declares a type.
     */
    public static function recordHelperSkip (string $file, string $kind): void
    {
        self::$helper_skips [] = ['file' => $file, 'kind' => $kind];
    }

    /**
     * Get helper skips (read-only).
     */
    public static function getHelperSkips (): array
    {
        return self::$helper_skips;
    }
}
