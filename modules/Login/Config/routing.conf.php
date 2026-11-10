<?php declare (strict_types=1);

/**
 * Login module route contributions (PLAN §8.4 A2).
 *
 * Additive only: this file may contribute `aliases` and nothing else.
 * The route previously lived in system/Config/routing.conf.php and was
 * migrated here so the module owns its route.
 *
 * @return array{aliases: array<string, string>}
 */
return [
    'aliases' => [
        'GET /login' => 'Login\Login::login',
    ],
];
