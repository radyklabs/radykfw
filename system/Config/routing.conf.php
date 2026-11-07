<?php declare (strict_types=1);

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Routing Configuration
 * File: system/Config/routing.conf.php
 *
 * Access via `get_config('routing.aliases')` etc. (dot-notation).
 *
 * @return array<string, mixed>
 */
return [
    // Priority: true = WordPress first (native paths), false = HMVC first
    'native' => true,
    // Default module when path is empty (module name only;
    // Router infers controller = module, method = 'index')
    'default' => 'Welcome',
    // Route aliases: path + HTTP method => controller::method
    // Format: 'METHOD /path' => 'Module\Controller::method' (explicit)
    //         'METHOD /path' => 'Module::method' (shorthand, controller = module)
    'aliases' => [
        'GET /login'                    => 'Login\Login::login',
        'GET /welcome'                  => 'Welcome\Welcome::index',
        'GET /documentation'            => 'Welcome\Welcome::documentation',
        'GET /changelog'                => 'Welcome\Welcome::changelog',
        // 'GET /welcome/home'          => 'Welcome\Welcome::home',
        // 'GET /welcome/door'          => 'Welcome\Welcome::door',
        
        // Pattern aliases: {seg} = string segment, {seg:int} = digits only
        // 'GET /sample-page'           => 'Welcome\Welcome::door',
        // 'GET /welcome/door/{a}/{b}'  => 'Welcome\Welcome::door',
        // 'GET /welcome/home/{id:int}' => 'Welcome\Welcome::home',
        // 'GET /dashboard'             => 'Dashboard\Dashboard::index',
        // 'GET /dashboard/stats'       => 'Dashboard\Dashboard::stats',
        // 'GET /api/health'            => 'Api\Api::health',
        // 'GET /api/user'              => 'Api\Api::user',
        // 'GET /api/posts'             => 'Api\Api::posts',
    ],
    // Paths that should NOT be intercepted by RadykFW (fall through to WP):
    // WP auth/admin tanpa .php + infra paths
    'skip_paths' => [
        'wp-admin',
        'wp-login',
        'wp-signup',
        'wp-activate',
        // 'login',
        // 'signup',
        // 'activate',
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
];
