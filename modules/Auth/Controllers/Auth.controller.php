<?php declare(strict_types=1);

namespace Modules\Auth\Controllers;

use System\Core\PrivateController;

/**
 * Auth controller — post-login validation gateway.
 *
 * URL: /auth
 * File: Auth.controller.php
 *
 * After successful login, user lands here. This controller:
 *   1. Verifies the session (is_user_logged_in)
 *   2. Redirects to /dashboard if valid
 *   3. Redirects back to /login if not logged in
 */
class Auth extends PrivateController
{
    /**
     * Post-login validation.
     *
     * URL: /auth
     */
    public static function index(): void
    {
        self::requireAuth();

        if (!is_user_logged_in()) {
            // Not authenticated — send back to login
            wp_safe_redirect(home_url(config('auth.login_url', '/login')));
            exit;
        }

        // Authenticated — proceed to dashboard
        wp_safe_redirect(home_url('/dashboard'));
        exit;
    }
}
