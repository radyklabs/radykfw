<?php declare(strict_types=1);

namespace Modules\Login\Controllers;

use System\Core\PublicController;

/**
 * Login controller
 * File: Login.controller.php
 */
class Login extends PublicController
{
    /**
     * Show login form.
     *
     * URL: /login
     */
    public static function login(): void
    {
        radykfw_asset('radyk-login-css', 'css/modules/login.css', [], false);
        radykfw_asset('radyk-login', 'js/modules/login.js', ['jquery'], true);
        view('login', [
            'username' => $_POST['username'] ?? '',
            'action_url' => home_url('/login/authenticate'),
        ]);
    }

    /**
     * Fallback index method.
     */
    public static function index(): void
    {
        self::login();
    }

    /**
     * Process login form submission.
     *
     * URL: /login/authenticate
     */
    public static function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::login();
            return;
        }

        if (!isset($_POST['radykfw_login_nonce']) || !wp_verify_nonce($_POST['radykfw_login_nonce'], 'radykfw_login')) {
            self::loginWithError(__('Invalid security token. Please try again.', 'radykfw'));
            return;
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            self::loginWithError(__('Username and password are required.', 'radykfw'));
            return;
        }

        $user = wp_signon([
            'user_login'  => $username,
            'user_password' => $password,
            'remember'    => $remember,
        ], false);

        if (is_wp_error($user)) {
            self::loginWithError($user->get_error_message());
            return;
        }

        // Regenerate session ID after successful login to prevent session fixation.
        if (function_exists ('session_regenerate_id')) {session_regenerate_id (true);}

        // Redirect to /auth for post-login validation, then to /dashboard
        wp_safe_redirect(home_url(get_config('auth.post_login_url', '/auth')));
        exit;
    }

    /**
     * Logout user.
     *
     * URL: /login/logout
     */
    public static function logout(): void
    {
        wp_logout();
        wp_safe_redirect(home_url(get_config('auth.post_logout_url', '/login')));
        exit;
    }

    /**
     * Store error in session and redirect to login page.
     */
    private static function loginWithError(string $error): void
    {
        $_SESSION['radykfw_login_error'] = $error;
        wp_safe_redirect(home_url('/login'));
        exit;
    }
}
