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
        echo '<tr><td>Admin Notices</td><td>' . count (\System\Services\AdminUI::getAdminNotices ()) . '</td></tr>';
        echo '<tr><td>Helper Skips</td><td>' . count (\System\Services\AdminUI::getHelperSkips ()) . '</td></tr>';
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
        echo '<tr><td>Admin Notices</td><td>' . count (\System\Services\AdminUI::getAdminNotices ()) . '</td></tr>';
        echo '<tr><td>Helper Skips</td><td>' . count (\System\Services\AdminUI::getHelperSkips ()) . '</td></tr>';
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
        echo '<p>Admin notices registry: ' . count (\System\Services\AdminUI::getAdminNotices ()) . ' notice(s) registered.</p>';
        echo '<p>Helper skips: ' . count (\System\Services\AdminUI::getHelperSkips ()) . ' file(s) skipped.</p>';
        echo '</div>';

        echo '</div>';
    }
}