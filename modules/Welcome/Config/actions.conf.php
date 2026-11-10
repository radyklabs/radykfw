<?php declare (strict_types=1);

/**
 * Welcome module nonce actions (PLAN §8.4 A2).
 *
 * Additive to system/Config/actions.conf.php; merged in stable module
 * order by Runner at boot. Names must match [A-Za-z0-9_-]+ and must not
 * duplicate system or other module actions.
 *
 * @return array{nonce_actions: list<string>}
 */
return [
    'nonce_actions' => [
        'welcome_action',
    ],
];
