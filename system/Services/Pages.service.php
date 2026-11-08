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

            // Time-based greeting
            $hour = (int) current_time ('H');
            if ($hour < 11) {
                $greeting = 'Good morning';
            } elseif ($hour < 15) {
                $greeting = 'Good afternoon';
            } elseif ($hour < 19) {
                $greeting = 'Good evening';
            } else {
                $greeting = 'Good night';
            }

            $user = wp_get_current_user ();
            $user_name = $user->display_name ?: $user->user_login ?: 'Admin';

            // Quick actions
            $quick_actions = [
                [
                    'icon'    => '⚙️',
                    'title'   => 'Settings',
                    'desc'    => 'Manage framework behavior, development options, and import/export of settings between environments.',
                    'url'     => admin_url ('options-general.php'),
                    'primary' => true,
                ],
                [
                    'icon'    => '🧩',
                    'title'   => 'Modules',
                    'desc'    => 'Enable, disable, and manage modules with routing debug.',
                    'url'     => admin_url ('admin.php?page=radyk-modules'),
                    'primary' => false,
                ],
                [
                    'icon'    => 'ℹ️',
                    'title'   => 'About',
                    'desc'    => 'System requirements, features, resources, and license information.',
                    'url'     => admin_url ('admin.php?page=radyk-about'),
                    'primary' => false,
                ],
            ];

            // Version badge
            $version_badge = 'RADYK FW ' . (defined('RADYKFW_VERSION') ? RADYKFW_VERSION : '1.0.10');

            // Maintenance mode check (placeholder - can be extended)
            $maintenance_mode = false;

            // Render via system template
            system_view ('pages/dashboard', [
                'page_title'     => 'Overview',
                'stats'          => $stats,
                'quick_actions'  => $quick_actions,
                'version_badge'  => $version_badge,
                'maintenance_mode' => $maintenance_mode,
                'greeting'       => $greeting,
                'user_name'      => $user_name,
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

        /**
         * Render the About admin page.
         */
        public static function renderAboutPage (): void
        {
            // Verify capability
            if (! current_user_can ('manage_options')) {wp_die ('Insufficient permissions');}

            // Prepare data for template
            $page_title = 'About RADYK';

            $requirements = [
                ['name' => 'PHP', 'version' => '8.2 or newer (8.3+ recommended)', 'badge' => 'required'],
                ['name' => 'WordPress', 'version' => '6.0 or newer (tested through 7.0)', 'badge' => 'required'],
                ['name' => 'MySQL / MariaDB', 'version' => 'MySQL 8.0+ or MariaDB 10.6+', 'badge' => ''],
                ['name' => 'mod_rewrite', 'version' => 'Apache module for clean permalinks', 'badge' => ''],
                ['name' => 'HTTPS', 'version' => 'SSL support recommended for production', 'badge' => ''],
            ];

            $features = [
                ['icon' => '🏗️', 'title' => 'HMVC Architecture', 'desc' => 'A hierarchical Model-View-Controller core with a 4-layer secure boot chain and passive class escaping.'],
                ['icon' => '🧩', 'title' => 'Modular Extensions', 'desc' => 'Auto-discovered extensions with HMAC integrity tokens — no standalone CLI tools, everything lives inside the plugin.'],
                ['icon' => '⚙️', 'title' => 'Service-Oriented Core', 'desc' => 'Orchestrator services with dependency resolution, container-based mutable state, and cache invalidation.'],
                ['icon' => '🛡️', 'title' => 'Security-First Policies', 'desc' => 'A single canonical save handler (nonce + capability + sanitization), masked-path serving, and source-disclosure deny rules.'],
                ['icon' => '🎨', 'title' => 'Design Token System', 'desc' => 'Semantic --ds-* tokens with light/dark/high-contrast themes, validated by offline CSS tests.'],
                ['icon' => '📐', 'title' => 'Layout Presets', 'desc' => 'Pick a layout purpose (blog / professional / shop): structure, style variations, and token remapping — without touching your content.'],
                ['icon' => '🌗', 'title' => 'Public Color Mode', 'desc' => 'Light/dark/auto with per-user override, inline anti-FOUC script, and editor-canvas matching.'],
                ['icon' => '🧭', 'title' => 'Full Site Editing', 'desc' => 'All templates and parts stay editable in the Site Editor — presets never overwrite user edits.'],
            ];

            $resources = [
                ['title' => 'README.md', 'url' => plugin_dir_url(__DIR__ . '/../../../') . 'README.md', 'desc' => 'Development & testing guide — CI, file naming scheme, and end-to-end setup.'],
                ['title' => 'WordPress Plugin Handbook', 'url' => 'https://developer.wordpress.org/plugins/', 'desc' => 'The official encyclopedia of WordPress plugin development.'],
            ];

            $final_notes = [
                'If you have any suggestions, ideas, or comments — or if you found a bug — report it through the project repository.',
                'RADYK has a robust extension API that makes extending the framework easy. If you are a developer interested in using it, see the documentation. You should not modify any of the core code.',
            ];

            $license_text = 'RADYK is free software, released under the MIT License. You may obtain a copy at https://opensource.org/licenses/MIT.';

            // Render via system template
            system_view ('pages/about', [
                'page_title'    => $page_title,
                'requirements'  => $requirements,
                'features'      => $features,
                'resources'     => $resources,
                'final_notes'   => $final_notes,
                'license_text'  => $license_text,
            ]);
        }
    }