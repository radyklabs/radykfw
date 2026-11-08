<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Assets — Admin asset enqueueing service.
 *
 * Handles conditional enqueueing of CSS/JS for admin pages.
 */
final class Assets
{
    private function __construct () {}

    /**
     * Enqueue admin assets for RADYK Labs pages.
     * Only loads on our own admin pages (slug starts with 'radyk').
     */
    public static function enqueueAdminAssets (): void
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