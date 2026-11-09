<?php declare (strict_types=1);

/**
 * Default Admin Actions Configuration
 *
 * Defines nonce actions for CSRF protection.
 * Can be overridden by child themes/plugins via config filter.
 *
 * @package RadykFW
 * @since 1.0.10
 */

return [
    'nonce_actions' => [
        'radyk_modules_action',
    ],
];