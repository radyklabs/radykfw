<?php declare (strict_types=1);

/**
 * Default Admin Menu Configuration
 *
 * Defines the default RADYK Labs admin menu structure.
 * Can be overridden by child themes/plugins via config filter.
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
            'callback'   => [\System\Services\Pages::class, 'renderDashboardPage'],
        ],
    ],

    'submenus' => [
        'radyk' => [
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