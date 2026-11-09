<?php declare (strict_types=1);

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested path could not be found.</p>');
}

/**
 * RadykFW Assets Configuration
 * File: system/Config/assets.conf.php
 *
 * Access via `get_config('assets.admin')`, `get_config('assets.frontend')`, etc.
 *
 * @return array<string, mixed>
 */
return [
    // Admin-only assets (load on all admin pages)
    'admin' => [
        'css' => [
            'radyk-admin' => [
                'src' => 'assets/css/admin.css',
                'deps' => [],
                'ver' => '1.0.0',
                'media' => 'all',
            ],
        ],
        'js' => [
            'radyk-admin' => [
                'src' => 'assets/js/admin.js',
                'deps' => ['jquery'],
                'ver' => '1.0.0',
                'in_footer' => true,
            ],
        ],
    ],

    // Radyk-specific admin pages (slug starts with 'radyk')
    'radyk_admin' => [
        'css' => [],
        'js' => [],
    ],

    // Frontend/public assets (load on frontend pages)
    'frontend' => [
        'css' => [
            'radyk-inter-font' => [
                'src' => 'assets/css/fonts/inter.css',
                'deps' => [],
                'ver' => '1.0.0',
                'media' => 'all',
            ],
        ],
        'js' => [],
    ],

    // Module-specific assets (loaded by modules themselves via view)
    'modules' => [
        'welcome' => [
            'css' => [
                'radyk-welcome' => [
                    'src' => 'assets/css/modules/welcome.css',
                    'deps' => [],
                    'ver' => '1.0.0',
                    'media' => 'all',
                ],
            ],
            'js' => [],
        ],
        'login' => [
            'css' => [
                'radyk-login' => [
                    'src' => 'assets/css/modules/login.css',
                    'deps' => [],
                    'ver' => '1.0.0',
                    'media' => 'all',
                ],
            ],
            'js' => [
                'radyk-login' => [
                    'src' => 'assets/js/modules/login.js',
                    'deps' => ['jquery'],
                    'ver' => '1.0.0',
                    'in_footer' => true,
                ],
            ],
        ],
    ],
];
