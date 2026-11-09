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
 * First-owner logic: the first product to register the top-level menu
 * becomes the "owner". Subsequent products reuse the existing menu
 * and only add their sub-menus.
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
    /** @var array<string, mixed> Top-level menu config (first-owner wins). */
    private static array $top_menu = [];

    /** @var array<int, array<string, mixed>> Sub-menus keyed by parent slug. */
    private static array $sub_menus = [];

    /** @var array<string, array<string, string>> Assets keyed by parent slug. */
    private static array $assets = [];

    /** @var bool Whether top-level menu has been registered. */
    private static bool $top_registered = false;


    /** @var array<int, array{message: string, in_page: string[]|string|null, type: string}> Admin notices registry. */
    private static array $admin_notices = [];

    /** @var array<int, array{file: string, kind: string}> Helper files skipped because they declare a type. */
    private static array $helper_skips = [];

    private function __construct () {}

    /**
     * Register (or claim) the top-level menu.
     * First caller wins — subsequent calls are ignored (first-owner logic).
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
        if ($slug === '') {return false;}

        if (self::$top_registered)
        {
            // Menu already owned by another product — ignore silently.
            return false;
        }

        self::$top_menu = [
            'slug'           => $slug,
            'title'          => $config ['title'] ?? 'RADYK',
            'capability'     => $config ['capability'] ?? 'manage_options',
            'icon'           => $config ['icon'] ?? 'dashicons-admin-generic',
            'position'       => $config ['position'] ?? 59,
            'callback'       => $config ['callback'] ?? null,
            'separator_after'=> $config ['separator_after'] ?? null,
        ];
        self::$top_registered = true;
        return true;
    }

    /**
     * Register a sub-menu under the top-level menu.
     *
     * @param string $parent_slug Parent menu slug (must match registerMenu slug).
     * @param array<string, mixed> $config
     *   - slug (string, required): sub-menu slug, e.g. 'radyk-modules'
     *   - title (string, required): display title
     *   - capability (string, default 'manage_options')
     *   - callback (callable, required): function to render the page
     *   - position (int|float|string, optional): menu position for ordering
     * @return bool True if registered, false if parent not found.
     */
    public static function registerSubmenu (string $parent_slug, array $config): bool
    {
        if (! self::$top_registered || self::$top_menu ['slug'] !== $parent_slug)
        {
            return false;
        }

        $slug = $config ['slug'] ?? '';
        if ($slug === '') {return false;}

        self::$sub_menus [$parent_slug] [] = [
            'slug'       => $slug,
            'title'      => $config ['title'] ?? '',
            'capability' => $config ['capability'] ?? 'manage_options',
            'callback'   => $config ['callback'] ?? null,
            'position'   => $config ['position'] ?? null,
        ];
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
        if (! self::$top_registered || self::$top_menu ['slug'] !== $parent_slug)
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
        return self::$top_menu;
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
        return self::$top_menu ['slug'] ?? '';
    }

    /**
     * Actually register the menus with WordPress.
     * Call this after all products have registered their menus/sub-menus.
     * Only runs once (first-owner logic).
     */
    public static function register (): void
    {
        if (! self::$top_registered) {return;}

        $menu = self::$top_menu;
        $slug = $menu ['slug'];

        // Add separator before top-level menu if configured
        if (isset ($menu ['separator_after'])) {
            self::addMenuSeparator ($menu ['separator_after'], '');
        }

        // Register top-level menu
        add_menu_page (
            $menu ['title'],
            $menu ['title'],
            $menu ['capability'],
            $slug,
            $menu ['callback'] ?? null,
            $menu ['icon'] ?? 'dashicons-admin-generic',
            $menu ['position'] ?? null
        );

        // Register sub-menus with position support
        $subs = self::$sub_menus [$slug] ?? [];
        if (! empty ($subs))
        {
            // Sort sub-menus by position if provided
            usort ($subs, function ($a, $b) {
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