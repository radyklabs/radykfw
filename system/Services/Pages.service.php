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
     * Render the Overview admin page (top-level RADYK menu).
     */
    public static function renderOverviewPage (): void
    {
        // Verify capability
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

        // Get routing aliases from config + module contributions
        $aliases = get_config ('routing.aliases', []) + (\System\Services\Module::discover ()['aliases'] ?? []);
        $native  = Settings::getConfigValue ('routing.native', get_config ('routing.native', true));
        $modules = Settings::getConfigValue ('modules.auto_discover', get_config ('modules.auto_discover', false));

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
                'url'     => admin_url ('admin.php?page=radyk-settings'),
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
                'icon'    => '🧪',
                'title'   => __('Tools', 'radykfw'),
                'desc'    => __('Shared Radyk Labs environment: branding and roadmap for future debug / cache / health tools.', 'radykfw'),
                'url'     => admin_url ('admin.php?page=radyk-tools'),
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

        // Maintenance mode check (placeholder - can be extended)
        $maintenance_mode = false;

        // Render via system template
        system_view ('pages/overview', [
            'page_title'      => __ ('Overview', 'radykfw'),
            'stats'           => $stats,
            'quick_actions'   => $quick_actions,
            'maintenance_mode'=> $maintenance_mode,
            'greeting'        => $greeting,
            'user_name'       => $user_name,
        ]);
    }

    /**
     * Render the Modules admin page (modules + routing).
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

        // Get routing aliases from config + module contributions
        $aliases = get_config ('routing.aliases', []) + (\System\Services\Module::discover ()['aliases'] ?? []);
        $native  = Settings::getConfigValue ('routing.native', get_config ('routing.native', true));
        $modules = Settings::getConfigValue ('modules.auto_discover', get_config ('modules.auto_discover', false));

        $modules_data = [];
        $discover = \System\Services\Module::discover ();
        if ($discover['errors'] !== []) {
            foreach ($discover['errors'] as $error) {
                $modules_data[] = [
                    'icon'        => '⚠️',
                    'name'        => $error,
                    'description' => __('Module discovery error', 'radykfw'),
                    'routes'      => '',
                ];
            }
        }
        foreach ($discover['modules'] as $name => $manifest) {
            $routes = [];
            foreach ($aliases as $route => $handler) {
                if (str_contains ($handler, $name . '\\')) {
                    $method = explode (' ', $route)[0] ?? '';
                    $path   = explode (' ', $route, 2)[1] ?? '';
                    $routes[] = $method . ' ' . $path;
                }
            }
            $modules_data[] = [
                'icon'        => '🧩',
                'name'        => $name,
                'description' => __('Module discovered via configuration.', 'radykfw'),
                'routes'      => implode (', ', $routes) ?: __('No routes registered.', 'radykfw'),
            ];
        }

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

        // Prepare data for template
        $stats = [
            'routing_mode'     => $native ? __ ('WordPress First (native)', 'radykfw') : __ ('HMVC First', 'radykfw'),
            'auto_discover'    => $modules ? __ ('Enabled', 'radykfw') : __ ('Disabled (alias-only)', 'radykfw'),
            'total_aliases'    => count ($aliases),
            'active_modules'   => count ($modules_data),
        ];

        $admin_notices_count = count (\System\Services\AdminUI::getAdminNotices ());
        $helper_skips_count  = count (\System\Services\AdminUI::getHelperSkips ());

        // Render via system template
        system_view ('pages/modules', [
            'stats'                => $stats,
            'modules'              => $modules_data,
            'routes'               => $routes_data,
            'admin_notices_count'  => $admin_notices_count,
            'helper_skips_count'   => $helper_skips_count,
        ]);
    }

    public static function renderSettingsPage (): void
    {
        if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

        $siteOverrides = Settings::getSavedConfig ('site');
        $userOverrides = Settings::getSavedConfig ('user');
        $siteLocale = Settings::getConfigValue ('admin_locale', get_locale (), 0);
        $userLocale = Settings::getConfigValue ('admin_locale', get_user_locale ());
        $siteSettings = [
            'admin_locale_override' => $siteOverrides['admin_locale'] ?? '',
            'admin_locale_effective' => $siteLocale,
            'routing.native' => Settings::getConfigValue (
                'routing.native',
                get_config ('routing.native', true),
                0
            ),
            'modules.auto_discover' => Settings::getConfigValue (
                'modules.auto_discover',
                get_config ('modules.auto_discover', false),
                0
            ),
        ];
        $userSettings = [
            'admin_locale_override' => $userOverrides['admin_locale'] ?? '',
            'admin_locale_effective' => $userLocale,
        ];
        $localeOptions = [
            'en_US' => __ ('English (United States)', 'radykfw'),
            'id_ID' => __ ('Indonesian', 'radykfw'),
        ];
        $availableLocales = function_exists ('get_available_languages')
            ? get_available_languages ()
            : [];
        foreach (array_merge ($availableLocales, [get_locale (), get_user_locale (), $siteLocale, $userLocale]) as $locale)
        {
            if (is_string ($locale)
                && preg_match ('/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8})*(?:@[A-Za-z0-9_-]+)?$/D', $locale)
                && ! isset ($localeOptions[$locale]))
            {
                $localeOptions[$locale] = $locale;
            }
        }

        $status = isset ($_GET['settings-updated']) && is_string ($_GET['settings-updated'])
            ? sanitize_key (wp_unslash ($_GET['settings-updated']))
            : '';

        system_view ('pages/settings', [
            'site_settings' => $siteSettings,
            'user_settings' => $userSettings,
            'locale_options' => $localeOptions,
            'saved_scope' => in_array ($status, ['site', 'user'], true) ? $status : '',
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
        $plugin_info = [
            'name'        => (string) plugin_meta ('Plugin Name'),
            'description' => (string) plugin_meta ('Description'),
            'version'     => (string) \System\Core\Registry::get ('version', ''),
            'author'      => (string) plugin_meta ('Author'),
            'plugin_uri'  => (string) plugin_meta ('Plugin URI'),
            'license'     => (string) plugin_meta ('License'),
            'license_uri' => (string) plugin_meta ('License URI'),
            'text_domain' => (string) \System\Core\Registry::get ('textdomain', ''),
        ];

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
            'plugin_info'   => $plugin_info,
            'requirements'  => $requirements,
            'features'      => $features,
            'resources'     => $resources,
            'final_notes'   => $final_notes,
            'license_text'  => $license_text,
        ]);
    }

                /**
                 * Render the Tools admin page (Radyk Labs branding + future feature list).
                 */
                public static function renderToolsPage (): void
                {
                    if (! current_user_can ('manage_options')) {wp_die (esc_html__ ('Insufficient permissions', 'radykfw'));}

                    $branding = [
                        'description' => __('Shared admin environment for Radyk Labs products.', 'radykfw'),
                        'products' => [
                            ['name' => 'RadykFW', 'role' => __('Framework & routing', 'radykfw'), 'status' => __('Active', 'radykfw')],
                            ['name' => 'RadykThemes', 'role' => __('Theme builder', 'radykfw'), 'status' => __('Future', 'radykfw')],
                            ['name' => 'RadykTools', 'role' => __('Ops & health checks', 'radykfw'), 'status' => __('Future', 'radykfw')],
                        ],
                    ];

                    $features = [
                        ['title' => __('Debug panel', 'radykfw'), 'desc' => __('Inspected request / route / config dumps.', 'radykfw')],
                        ['title' => __('Cache clear', 'radykfw'), 'desc' => __('Flush Drip + module discovery caches.', 'radykfw')],
                        ['title' => __('Health check', 'radykfw'), 'desc' => __('Report route, config, and asset diagnostics.', 'radykfw')],
                    ];

                    system_view ('pages/tools', [
                        'page_title' => __('Tools', 'radykfw'),
                        'branding'   => $branding,
                        'features'   => $features,
                    ]);
                }
}
