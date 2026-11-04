<?php declare(strict_types=1);

namespace Modules\Api\Controllers;

use System\Core\ApiController;

/**
 * REST API endpoints for RadykFW.
 * File: Api.controller.php
 * Base URL: /api
 */
class Api extends ApiController
{
    /**
     * Health check endpoint.
     *
     * URL: /api/health
     * Response: { status: "ok", version: "1.0.1" }
     */
    public static function health(): void
    {
        self::respond([
            'status'  => 'ok',
            'version' => \System\Core\Registry::get('version', '1.0.1'),
        ]);
    }

    /**
     * Current user info (requires auth).
     *
     * URL: /api/user
     * Response: { id, name, email }
     */
    public static function user(): void
    {
        if (!is_user_logged_in()) {
            self::error('Unauthorized', 401);
        }
        $user = wp_get_current_user();
        self::respond([
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ]);
    }

    /**
     * Generic list endpoint with pagination.
     *
     * URL: /api/posts?page=1&per_page=10
     */
    public static function posts(int $page = 1, int $per_page = 10): void
    {
        if (!is_user_logged_in()) {
            self::error('Unauthorized', 401);
        }
        // Implement actual query here...
        self::respond([
            'page'      => $page,
            'per_page'  => $per_page,
            'data'      => [],
        ]);
    }
}
