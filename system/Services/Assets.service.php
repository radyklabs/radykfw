<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Assets — Asset enqueueing service.
 *
 * Handles conditional enqueueing of CSS/JS for admin and frontend pages.
 * Configuration via system/Config/assets.conf.php
 */
final class Assets
{
    private function __construct () {}

    /**
     * Enqueue admin assets for all admin pages.
     * Loads assets from config 'assets.admin' section.
     */
    public static function enqueueAdminAssets (): void
    {
        $config = get_config ('assets.admin', []);
        self::enqueueAssets ($config);
    }

    /**
     * Enqueue Radyk-specific admin assets.
     * Only loads on Radyk admin pages (slug starts with 'radyk').
     * Loads assets from config 'assets.radyk_admin' section.
     */
    public static function enqueueRadykAdminAssets (): void
    {
        $screen = function_exists ('get_current_screen') ? get_current_screen () : null;
        $slug   = $screen?->id ?? '';

        // Only enqueue on our pages
        if (! str_starts_with ($slug, 'radyk')) {return;}

        $config = get_config ('assets.radyk_admin', []);
        self::enqueueAssets ($config);
    }

    /**
     * Enqueue frontend assets.
     * Loads assets from config 'assets.frontend' section.
     */
    public static function enqueueFrontendAssets (): void
    {
        if (is_admin ()) {return;}

        $config = get_config ('assets.frontend', []);
        self::enqueueAssets ($config);
    }

    /**
     * Enqueue module-specific assets.
     * Called by modules from their views/controllers.
     *
     * @param string $module Module name (e.g., 'welcome', 'login')
     */
    public static function enqueueModuleAssets (string $module): void
    {
        $config = get_config ("assets.modules.{$module}", []);
        self::enqueueAssets ($config);
    }

    /**
     * Internal: enqueue assets from config array.
     *
     * @param array<string, mixed> $config Config with 'css' and 'js' keys
     */
    private static function enqueueAssets (array $config): void
    {
        $baseUrl = plugin_dir_url (keyfile);

        // Enqueue CSS
        if (isset ($config['css']) && is_array ($config['css'])) {
            foreach ($config['css'] as $handle => $asset) {
                $src = $baseUrl . ($asset['src'] ?? '');
                $deps = $asset['deps'] ?? [];
                $ver = $asset['ver'] ?? '1.0.0';
                $media = $asset['media'] ?? 'all';
                wp_enqueue_style ($handle, $src, $deps, $ver, $media);
            }
        }

        // Enqueue JS
        if (isset ($config['js']) && is_array ($config['js'])) {
            foreach ($config['js'] as $handle => $asset) {
                $src = $baseUrl . ($asset['src'] ?? '');
                $deps = $asset['deps'] ?? [];
                $ver = $asset['ver'] ?? '1.0.0';
                $inFooter = $asset['in_footer'] ?? false;
                wp_enqueue_script ($handle, $src, $deps, $ver, $inFooter);
            }
        }
    }
}
