<?php declare (strict_types=1);

/**
 * Welcome module route contributions (PLAN §8.4 A2).
 *
 * Additive only: this file may contribute `aliases` and nothing else —
 * system routing policy (native, default, skip_paths, skip_extensions)
 * lives in system/Config/routing.conf.php and cannot be overridden here.
 * Duplicate keys or duplicate placeholder shapes fail the whole catalog.
 *
 * @return array{aliases: array<string, string>}
 */
return [
    'aliases' => [
        // Explicit: 'METHOD /path' => 'Module\Controller::method'
        'GET /test'           => 'Welcome\Welcome::test',
        'GET /welcome'        => 'Welcome\Welcome::index',
        'GET /documentation'  => 'Welcome\Welcome::documentation',
        'GET /changelog'      => 'Welcome\Welcome::changelog',
        'GET /welcome/home'   => 'Welcome\Welcome::home',
        'GET /welcome/door'   => 'Welcome\Welcome::door',

        // Pattern aliases (validated by System\Services\Pipeline):
        // 'GET /welcome/door/{slug}'      => 'Welcome\Welcome::door',
        // 'GET /welcome/door/{id:int}'    => 'Welcome\Welcome::door',
    ],
];
