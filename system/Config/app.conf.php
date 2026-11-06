<?php declare(strict_types=1);

/**
 * RadykFW Application Configuration
 * File: system/Config/app.conf.php
 *
 * Loaded via the `config()` helper (dot-notation access) or directly.
 * Single source of truth for plugin runtime settings.
 *
 * @return array<string, mixed>
 */
return [
    // --- Plugin identity ---
    'app' => [
        'name'       => 'RadykFW',
        'version'    => version ?? '1.0.1',
        'debug'      => defined('WP_DEBUG') && WP_DEBUG,
        'timezone'   => 'UTC',
        'locale'     => 'en_US',
    ],

    // --- Module behavior ---
    'modules' => [
        // Auto-discover controllers via autoloader on each request
        'auto_discover'    => true,
        // Namespace prefix for module classes (derived from .starter modules_dir)
        // 'namespace_prefix' => 'Modules', // Derived from .starter modules_dir
    ],

    // --- Routing ---
    'routing' => [
            // Priority: true = WordPress first (native paths), false = HMVC first
            'native' => true,
            // Default module when path is empty (module name only;
            // Router infers controller = module, method = 'index')
            'default' => 'Welcome',
            // Route aliases: path + HTTP method => controller::method
            // Format: 'METHOD /path' => 'Module\Controller::method' (explicit)
            //         'METHOD /path' => 'Module::method' (shorthand, controller = module)
            'aliases' => [
                'GET /login' => 'Login\Login::login',
                'POST /login' => 'Login\Login::login',
                'GET /auth'  => 'Auth\Auth::index',
                // 'GET /welcome' => 'Welcome\Welcome::index',
                // 'GET /welcome/home' => 'Welcome\Welcome::home',
                // 'GET /welcome/door' => 'Welcome\Welcome::door',
                // Pattern aliases: {seg} = string segment, {seg:int} = digits only
                'GET /sample-page'      => 'Welcome\Welcome::door',
                // 'GET /welcome/door/{a}/{b}'  => 'Welcome\Welcome::door',
                // 'GET /welcome/home/{id:int}' => 'Welcome\Welcome::home',
                // 'GET /dashboard' => 'Dashboard\Dashboard::index',
                // 'GET /dashboard/stats' => 'Dashboard\Dashboard::stats',
                // 'GET /api/health' => 'Api\Api::health',
                // 'GET /api/user' => 'Api\Api::user',
                // 'GET /api/posts' => 'Api\Api::posts',
            ],
            // Paths that should NOT be intercepted by RadykFW (fall through to WP):
            // WP auth/admin tanpa .php + infra paths
            'skip_paths' => [
                'wp-admin',
                'wp-login',
                'wp-signup',
                'wp-activate',
                'login',
                'signup',
                'activate',
                'wp-json',
                'xmlrpc',
                'feed',
                'sitemap',
            ],
            // Static file extensions to skip
            'skip_extensions' => [
                'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg',
                'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'webp',
            ],
        ],

    // --- Auth ---
    'auth' => [
        // URL to redirect to after successful login
        'post_login_url' => '/auth',
        // URL to redirect to after logout
        'post_logout_url' => '/login',
        // URL for login page
        'login_url'      => '/login',
    ],
];
