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
        // Default module/controller when path is empty
        'default' => [
            'module'     => 'Welcome',
            'controller' => 'Welcome',
            'method'     => 'index',
        ],
        // Route aliases: path + HTTP method => controller::method
        // Format: 'METHOD /path' => 'Module\Controllers\Controller::method'
        'aliases' => [
            'POST /login'  => 'Auth\Controllers\Custom::login',
            'GET  /auth'   => 'Auth\Controllers\Auth::index',
        ],
        // Paths that should NOT be intercepted by RadykFW (fall through to WP)
        'skip_paths' => [
            'wp-admin',
            'wp-login',
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
