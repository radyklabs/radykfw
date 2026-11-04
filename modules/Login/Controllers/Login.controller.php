<?php declare(strict_types=1);

namespace Modules\Login\Controllers;

/**
 * Login controller (static methods)
 * File: Login.controller.php
 */
class Login
{
    /**
     * Show login form.
     *
     * URL: /login
     * Routing: module=Login, controller=Login, method=login (fallback to index)
     */
    public static function login(): void
    {
        // Render view
        view('modules/Login/Views/login.view');
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
     * Routing: module=Login, controller=Login, method=authenticate
     */
    public static function authenticate(): void
    {
        // Check if POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::login();
            return;
        }

        // Verify nonce
        if (!isset($_POST['radykfw_login_nonce']) || !wp_verify_nonce($_POST['radykfw_login_nonce'], 'radykfw_login')) {
            self::loginWithError('Invalid security token. Please try again.');
            return;
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            self::loginWithError('Username and password are required.');
            return;
        }

        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ], false);

        if (is_wp_error($user)) {
            self::loginWithError($user->get_error_message());
            return;
        }

        // Login successful
        wp_safe_redirect(home_url('/dashboard'));
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
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    /**
     * Render login view with error.
     */
    private static function loginWithError(string $error): void
    {
        // Include view with error variable
        $error = $error;
        include \System\Core\Registry::get ('modules') . '/Login/Views/login.view.php';
    }
}