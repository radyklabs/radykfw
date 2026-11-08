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

            // Active modules
            $modules_data = [
                [
                    'icon'        => '🔌',
                    'name'        => 'Api',
                    'description' => 'REST API endpoints for health checks, user data, and posts.',
                    'routes'      => 'GET /api/health, GET /api/user, GET /api/posts',
                ],
                [
                    'icon'        => '🔐',
                    'name'        => 'Auth',
                    'description' => 'Authentication and authorization services.',
                    'routes'      => 'Internal service module',
                ],
                [
                    'icon'        => '📊',
                    'name'        => 'Dashboard',
                    'description' => 'Admin dashboard with stats and overview.',
                    'routes'      => 'GET /dashboard, GET /dashboard/stats',
                ],
                [
                    'icon'        => '🔑',
                    'name'        => 'Login',
                    'description' => 'Custom login page and authentication flow.',
                    'routes'      => 'GET /login',
                ],
                [
                    'icon'        => '👋',
                    'name'        => 'Welcome',
                    'description' => 'Welcome page, documentation, and changelog.',
                    'routes'      => 'GET /welcome, GET /documentation, GET /changelog',
                ],
            ];

            // Registered routes from routing.conf.php
            $routes_data = [];
            foreach ($aliases as $route => $handler) {
                if (preg_match('/^(\w+)\s+(.+)$/', $route, $matches)) {
                    $routes_data[] = [
                        'method'  => $matches[1],
                        'path'    => $matches[2],
                        'handler' => $handler,
                    ];
                }
            }

            // Version badge
            $version_badge = 'RadykFW ' . \System\Core\Registry::get ('version');

            // Maintenance mode check (placeholder - can be extended)
            $maintenance_mode = false;

            // Render via system template
            system_view ('pages/dashboard', [
                'page_title'      => 'Overview',
                'stats'           => $stats,
                'quick_actions'   => $quick_actions,
                'version_badge'   => $version_badge,
                'maintenance_mode'=> $maintenance_mode,
                'greeting'        => $greeting,
                'user_name'       => $user_name,
                'modules'         => $modules_data,
                'routes'          => $routes_data,
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
                    $page_title = 'About RadykFW';

            $requirements = [
                        ['name' => 'PHP', 'version' => '8.3 or newer (8.3+ recommended)', 'badge' => 'required'],
                        ['name' => 'WordPress', 'version' => '6.0 or newer (tested through 7.1)', 'badge' => 'required'],
                ['name' => 'MySQL / MariaDB', 'version' => 'MySQL 8.0+ or MariaDB 10.6+', 'badge' => ''],
                ['name' => 'mod_rewrite', 'version' => 'Apache module for clean permalinks', 'badge' => ''],
                ['name' => 'HTTPS', 'version' => 'SSL support recommended for production', 'badge' => ''],
            ];

            $features = [
                        ['icon' => '🏗️', 'title' => 'HMVC Architecture', 'desc' => 'Hierarchical Model-View-Controller core with custom autoloader, Registry pattern, and strict typing.'],
                        ['icon' => '🧩', 'title' => 'Modular Extensions', 'desc' => 'Self-contained modules (Api, Auth, Dashboard, Login, Welcome) with Controllers, Models, Views, and Services.'],
                ['icon' => '⚙️', 'title' => 'Service-Oriented Core', 'desc' => 'Orchestrator services with dependency resolution, container-based mutable state, and cache invalidation.'],
                        ['icon' => '🛡️', 'title' => 'Security-First Policies', 'desc' => 'Prepared statements, nonce verification, capability checks, output encoding, and input validation.'],
                        ['icon' => '🎯', 'title' => 'Custom Routing', 'desc' => 'Explicit alias routes with WordPress-native priority (routing.native=true), pattern matching with {seg:int}.'],
                        ['icon' => '📦', 'title' => 'Standalone / Zero Deps', 'desc' => 'No Composer at runtime, custom SPL autoloader, no third-party dependencies.'],
                        ['icon' => '🔧', 'title' => 'Developer Friendly', 'desc' => 'PHP 8.3+, strict_types, split config files, convention-based structure.'],
                        ['icon' => '⚡', 'title' => 'High Performance', 'desc' => 'Convention-based autoloading, zero runtime dependencies, optimized boot chain.'],
            ];

            $resources = [
                ['title' => 'README.md', 'url' => plugin_dir_url(__DIR__ . '/../../../') . 'README.md', 'desc' => 'Development & testing guide — CI, file naming scheme, and end-to-end setup.'],
                        ['title' => 'GitHub Repository', 'url' => 'https://github.com/radyklabs/radykfw', 'desc' => 'Source code, issues, pull requests, and releases.'],
                        ['title' => 'WordPress Plugin Handbook', 'url' => 'https://developer.wordpress.org/plugins/', 'desc' => 'The official encyclopedia of WordPress plugin development.'],
                    ];

                    $final_notes = [
                        'If you have any suggestions, ideas, or comments — or if you found a bug — report it through the GitHub repository.',
                        'RadykFW has a robust extension API that makes extending the framework easy. If you are a developer interested in using it, see the documentation. You should not modify any of the core code.',
                    ];

                    $license_text = 'RadykFW is free software, released under the MIT License. You may obtain a copy at https://opensource.org/licenses/MIT.';

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