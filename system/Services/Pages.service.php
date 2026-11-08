<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Pages — Admin page renderers.
 *
 * Stateless renderers for admin pages. Called by AdminUI callbacks.
 * Uses system_view() to render templates from system/Templates/pages/*.page.php
 */
final class Pages
{
    private function __construct () {}

    /**
     * Render the Dashboard admin page (top-level RADYK menu).
     */
    public static function renderDashboardPage (): void
    {
        // Verify capability
        if (! current_user_can ('manage_options')) {wp_die ('Insufficient permissions');}

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);
        $modules = get_config ('modules.auto_discover', false);

        // Prepare data for template
        $stats = [
            'routing_mode'     => $native ? 'WordPress First (native)' : 'HMVC First',
            'auto_discover'    => $modules ? 'Enabled' : 'Disabled (alias-only)',
            'total_aliases'    => count ($aliases),
            'admin_notices'    => count (\System\Services\AdminUI::getAdminNotices ()),
            'helper_skips'     => count (\System\Services\AdminUI::getHelperSkips ()),
        ];

        $quick_links = [
            ['label' => 'Modules', 'url' => admin_url ('admin.php?page=radyk-modules'), 'secondary' => false],
            ['label' => 'Settings', 'url' => admin_url ('options-general.php'), 'secondary' => true],
            ['label' => 'Plugins', 'url' => admin_url ('plugins.php'), 'secondary' => true],
        ];

        // Render via system template
        system_view ('pages/dashboard', [
            'stats'       => $stats,
            'quick_links' => $quick_links,
        ]);
    }

    /**
     * Render the Modules admin page (Overview + Routing).
     */
    public static function renderModulesPage (): void
    {
        // Verify capability
        if (! current_user_can ('manage_options')) {wp_die ('Insufficient permissions');}

        // Verify nonce only on POST (form submissions)
        if ($_SERVER ['REQUEST_METHOD'] === 'POST')
        {
            if (! \System\Services\Actions::verifyNonce ('radyk_modules_action')) {wp_die ('Invalid nonce');}
        }

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);
        $modules = get_config ('modules.auto_discover', false);

        // Prepare data for template
        $stats = [
            'routing_mode'     => $native ? 'WordPress First (native)' : 'HMVC First',
            'auto_discover'    => $modules ? 'Enabled' : 'Disabled (alias-only)',
            'total_aliases'    => count ($aliases),
        ];

        $admin_notices_count = count (\System\Services\AdminUI::getAdminNotices ());
        $helper_skips_count  = count (\System\Services\AdminUI::getHelperSkips ());

        // Render via system template
        system_view ('pages/modules', [
            'stats'                => $stats,
            'aliases'              => $aliases,
            'admin_notices_count'  => $admin_notices_count,
            'helper_skips_count'   => $helper_skips_count,
        ]);
    }
}