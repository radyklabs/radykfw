<?php declare (strict_types=1);

/**
 * Welcome module admin menu contributions (PLAN §8.4 A2).
 *
 * Mirrors the shape of system/Config/menus.conf.php; entries merge
 * additively under the radykfw_admin_menu_config filter. Welcome is a
 * frontend module with no admin render callback yet, so both lists are
 * empty. Example once an admin page exists:
 *
 *   'submenus' => [
 *       'radyk' => [[
 *           'slug'       => 'radyk-welcome',
 *           'title'      => 'Welcome',
 *           'capability' => 'manage_options',
 *           'callback'   => [Modules\Welcome\Controllers\Welcome::class, 'adminIndex'],
 *           'position'   => 6,
 *       ]],
 *   ],
 *
 * @return array{menus: list<array<string, mixed>>, submenus: array<string, list<array<string, mixed>>>}
 */
return [
    'menus'    => [],
    'submenus' => [],
];
