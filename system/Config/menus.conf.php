<?php declare (strict_types=1);

/**
 * Default Admin Menu Configuration
 *
 * Defines the default RADYK Labs admin menu structure.
 * Modules extend this configuration through their own Configs/menus.conf.php.
 *
 * @package RadykFW
 * @since 1.0.10
 */

return [
    'menus' => [
        [
            'slug'       => 'radyk',
            'title'      => 'RADYK',
            'capability' => 'manage_options',
            'icon'       => 'dashicons-superhero',
            'position'   => 59.1,
            'callback'   => [\System\Services\Pages::class, 'renderOverviewPage'],
        ],
    ],

    'submenus' => [
        'radyk' => [
            [
                'slug'       => 'radyk',
                'title'      => __ ('Overview', 'radykfw'),
                'capability' => 'manage_options',
                'callback'   => [\System\Services\Pages::class, 'renderOverviewPage'],
                'position'   => 1,
            ],
            [
                'slug'       => 'radyk-modules',
                'title'      => __ ('Modules', 'radykfw'),
                'capability' => 'manage_options',
                'callback'   => [\System\Services\Pages::class, 'renderModulesPage'],
                'position'   => 5,
            ],
            [
                'slug'       => 'radyk-settings',
                'title'      => __ ('Settings', 'radykfw'),
                'capability' => 'manage_options',
                'callback'   => [\System\Services\Pages::class, 'renderSettingsPage'],
                'position'   => 90,
            ],
            [
                'slug'       => 'radyk-about',
                'title'      => __ ('About', 'radykfw'),
                'capability' => 'manage_options',
                'callback'   => [\System\Services\Pages::class, 'renderAboutPage'],
                'position'   => 99,
            ],
        ],
    ],

    'separator' => [
        'position' => 59.9,
    ],
];
