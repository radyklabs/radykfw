<?php declare(strict_types=1);

/**
 * RadykFW Auth Configuration
 * File: system/Config/auth.conf.php
 *
 * Access via `get_config('auth.post_login_url')` etc. (dot-notation).
 *
 * @return array<string, mixed>
 */
return [
    // URL to redirect to after successful login
    'post_login_url' => '/auth',
    // URL to redirect to after logout
    'post_logout_url' => '/login',
    // URL for login page
    'login_url'      => '/login',
];
