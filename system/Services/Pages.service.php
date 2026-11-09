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
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);
        $modules = get_config ('modules.auto_discover', false);

        // Prepare data for template
        $stats = [
            'routing_mode'     => $native ? __ ('WordPress First (native)', 'radykfw') : __ ('HMVC First', 'radykfw'),
            'auto_discover'    => $modules ? __ ('Enabled', 'radykfw') : __ ('Disabled (alias-only)', 'radykfw'),
            'total_aliases'    => count ($aliases),
            'admin_notices'    => count (\System\Services\AdminUI::getAdminNotices ()),
            'helper_skips'     => count (\System\Services\AdminUI::getHelperSkips ()),
        ];

            // Time-based greeting
            $hour = (int) current_time ('H');
            if ($hour < 11) {
                $greeting = __ ('Good morning', 'radykfw');
            } elseif ($hour < 15) {
                $greeting = __ ('Good afternoon', 'radykfw');
            } elseif ($hour < 19) {
                $greeting = __ ('Good evening', 'radykfw');
            } else {
                $greeting = __ ('Good night', 'radykfw');
            }

            $user = wp_get_current_user ();
            $user_name = $user->display_name ?: $user->user_login ?: __ ('Admin', 'radykfw');

            // Quick actions
            $quick_actions = [
                [
                    'icon'    => '⚙️',
                    'title'   => __ ('Settings', 'radykfw'),
                    'desc'    => __ ('Manage framework behavior, development options, and import/export of settings between environments.', 'radykfw'),
                    'url'     => admin_url ('options-general.php'),
                    'primary' => true,
                ],
                [
                    'icon'    => '🧩',
                    'title'   => __ ('Modules', 'radykfw'),
                    'desc'    => __ ('Enable, disable, and manage modules with routing debug.', 'radykfw'),
                    'url'     => admin_url ('admin.php?page=radyk-modules'),
                    'primary' => false,
                ],
                [
                    'icon'    => 'ℹ️',
                    'title'   => __ ('About', 'radykfw'),
                    'desc'    => __ ('System requirements, features, resources, and license information.', 'radykfw'),
                    'url'     => admin_url ('admin.php?page=radyk-about'),
                    'primary' => false,
                ],
            ];

            // Active modules
            $modules_data = [
                [
                    'icon'        => '🔌',
                    'name'        => 'Api',
                    'description' => __ ('REST API endpoints for health checks, user data, and posts.', 'radykfw'),
                    'routes'      => 'GET /api/health, GET /api/user, GET /api/posts',
                ],
                [
                    'icon'        => '🔐',
                    'name'        => 'Auth',
                    'description' => __ ('Authentication and authorization services.', 'radykfw'),
                    'routes'      => __ ('Internal service module', 'radykfw'),
                ],
                [
                    'icon'        => '📊',
                    'name'        => 'Dashboard',
                    'description' => __ ('Admin dashboard with stats and overview.', 'radykfw'),
                    'routes'      => 'GET /dashboard, GET /dashboard/stats',
                ],
                [
                    'icon'        => '🔑',
                    'name'        => 'Login',
                    'description' => __ ('Custom login page and authentication flow.', 'radykfw'),
                    'routes'      => 'GET /login',
                ],
                [
                    'icon'        => '👋',
                    'name'        => 'Welcome',
                    'description' => __ ('Welcome page, documentation, and changelog.', 'radykfw'),
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
                'page_title'      => __ ('Overview', 'radykfw'),
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
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

        // Verify nonce only on POST (form submissions)
        if ($_SERVER ['REQUEST_METHOD'] === 'POST')
        {
            if (! \System\Services\Actions::verifyNonce ('radyk_modules_action')) {wp_die (esc_html__ ('Invalid nonce', 'radykfw'));}
        }

        // Get routing aliases from config
        $aliases = get_config ('routing.aliases', []);
        $native  = get_config ('routing.native', true);
        $modules = get_config ('modules.auto_discover', false);

        // Prepare data for template
        $stats = [
            'routing_mode'     => $native ? __ ('WordPress First (native)', 'radykfw') : __ ('HMVC First', 'radykfw'),
            'auto_discover'    => $modules ? __ ('Enabled', 'radykfw') : __ ('Disabled (alias-only)', 'radykfw'),
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
            if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

            // Prepare data for template
                    $page_title = __ ('About RadykFW', 'radykfw');

            $requirements = [
                        ['name' => 'PHP', 'version' => __ ('8.3 or newer (8.3+ recommended)', 'radykfw'), 'badge' => __ ('required', 'radykfw')],
                        ['name' => 'WordPress', 'version' => __ ('6.0 or newer (tested through 7.1)', 'radykfw'), 'badge' => __ ('required', 'radykfw')],
                ['name' => 'MySQL / MariaDB', 'version' => __ ('MySQL 8.0+ or MariaDB 10.6+', 'radykfw'), 'badge' => ''],
                ['name' => 'mod_rewrite', 'version' => __ ('Apache module for clean permalinks', 'radykfw'), 'badge' => ''],
                ['name' => 'HTTPS', 'version' => __ ('SSL support recommended for production', 'radykfw'), 'badge' => ''],
            ];

            $features = [
                        ['icon' => '🏗️', 'title' => __ ('HMVC Architecture', 'radykfw'), 'desc' => __ ('Hierarchical Model-View-Controller core with custom autoloader, Registry pattern, and strict typing.', 'radykfw')],
                        ['icon' => '🧩', 'title' => __ ('Modular Extensions', 'radykfw'), 'desc' => __ ('Self-contained modules (Api, Auth, Dashboard, Login, Welcome) with Controllers, Models, Views, and Services.', 'radykfw')],
                ['icon' => '⚙️', 'title' => __ ('Service-Oriented Core', 'radykfw'), 'desc' => __ ('Orchestrator services with dependency resolution, container-based mutable state, and cache invalidation.', 'radykfw')],
                        ['icon' => '🛡️', 'title' => __ ('Security-First Policies', 'radykfw'), 'desc' => __ ('Prepared statements, nonce verification, capability checks, output encoding, and input validation.', 'radykfw')],
                        ['icon' => '🎯', 'title' => __ ('Custom Routing', 'radykfw'), 'desc' => __ ('Explicit alias routes with WordPress-native priority (routing.native=true), pattern matching with {seg:int}.', 'radykfw')],
                        ['icon' => '📦', 'title' => __ ('Standalone / Zero Deps', 'radykfw'), 'desc' => __ ('No Composer at runtime, custom SPL autoloader, no third-party dependencies.', 'radykfw')],
                        ['icon' => '🔧', 'title' => __ ('Developer Friendly', 'radykfw'), 'desc' => __ ('PHP 8.3+, strict_types, split config files, convention-based structure.', 'radykfw')],
                        ['icon' => '⚡', 'title' => __ ('High Performance', 'radykfw'), 'desc' => __ ('Convention-based autoloading, zero runtime dependencies, optimized boot chain.', 'radykfw')],
            ];

            $resources = [
                ['title' => 'README.md', 'url' => plugin_dir_url(__DIR__ . '/../../../') . 'README.md', 'desc' => __ ('Development & testing guide — CI, file naming scheme, and end-to-end setup.', 'radykfw')],
                        ['title' => __ ('GitHub Repository', 'radykfw'), 'url' => 'https://github.com/radyklabs/radykfw', 'desc' => __ ('Source code, issues, pull requests, and releases.', 'radykfw')],
                        ['title' => __ ('WordPress Plugin Handbook', 'radykfw'), 'url' => 'https://developer.wordpress.org/plugins/', 'desc' => __ ('The official encyclopedia of WordPress plugin development.', 'radykfw')],
                    ];

                    $final_notes = [
                        __ ('If you have any suggestions, ideas, or comments — or if you found a bug — report it through the GitHub repository.', 'radykfw'),
                        __ ('RadykFW has a robust extension API that makes extending the framework easy. If you are a developer interested in using it, see the documentation. You should not modify any of the core code.', 'radykfw'),
                    ];

                    $license_text = __ ('RadykFW is free software, released under the MIT License. You may obtain a copy at https://opensource.org/licenses/MIT.', 'radykfw');

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