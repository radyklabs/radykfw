<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykLabs Admin UI Registry — shared environment for RADYK products.
 *
 * First-owner logic: the first product to register the top-level menu
 * becomes the "owner". Subsequent products reuse the existing menu
 * and only add their sub-menus.
 *
 * Usage (from any product, e.g. RadykFW, RadykThemes):
 *   RadykLabs::registerMenu ([
 *       'slug'       => 'radyk',
 *       'title'      => 'RADYK',
 *       'capability' => 'manage_options',
 *       'icon'       => 'dashicons-admin-generic',
 *       'position'   => 59,
 *   ]);
 *   RadykLabs::registerSubmenu ('radyk', [
 *       'slug'       => 'radyk-modules',
 *       'title'      => 'Modules',
 *       'capability' => 'manage_options',
 *       'callback'   => [MyModuleController::class, 'render'],
 *   ]);
 *   RadykLabs::registerAssets ('radyk', [
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

    /** @var array<string, string> Nonce actions registered. */
    private static array $nonce_actions = [];

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
     *   - position (int, default 59)
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
            'slug'       => $slug,
            'title'      => $config ['title'] ?? 'RADYK',
            'capability' => $config ['capability'] ?? 'manage_options',
            'icon'       => $config ['icon'] ?? 'dashicons-admin-generic',
            'position'   => $config ['position'] ?? 59,
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
     * Register a nonce action for CSRF protection.
     */
    public static function registerNonceAction (string $action): void
    {
        self::$nonce_actions [] = $action;
    }

    /**
     * Verify a nonce for a registered action.
     */
    public static function verifyNonce (string $action, string $nonce_field = '_wpnonce'): bool
    {
        if (! in_array ($action, self::$nonce_actions, true)) {return false;}
        return wp_verify_nonce ($_POST [$nonce_field] ?? '', $action);
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

            // Register sub-menus
            foreach (self::$sub_menus [$slug] ?? [] as $sub)
            {
                add_submenu_page (
                    $slug,
                    $sub ['title'],
                    $sub ['title'],
                    $sub ['capability'],
                    $sub ['slug'],
                    $sub ['callback'] ?? null
                );
            }
        }
    }