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
    // Sample nonce action: the Modules admin page does not yet POST a form,
    // so this entry only demonstrates how module/admin nonce actions are
    // declared. Register/verify via Actions::verifyNonce() when a form lands.
    'nonce_actions' => [
        'radyk_modules_action',
    ],
];
