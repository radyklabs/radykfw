<?php declare(strict_types=1);

namespace Modules\Api\Controllers;

use System\Core\ApiController;
use Modules\Api\Models\Posts;

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
        // H10: restrict sensitive fields (email) to users with manage_options.
        $allowEmail = current_user_can ('manage_options');
        $user = wp_get_current_user ();
        $payload = [
            'id'   => $user->ID,
            'name' => $user->display_name,
        ];
        if ($allowEmail) {
            $payload['email'] = $user->user_email;
        }
        self::respond ($payload);
    }

    /**
     * Generic list endpoint with pagination.
     *
     * URL: /api/posts?page=1&per_page=10
     */
    public static function posts (int $page = 1, int $per_page = 10): void
    {
        if (!is_user_logged_in ())
        {
            self::error ('Unauthorized', 401);
        }

        $records = Posts::list ($page, $per_page);
        $data = \array_map (function ($r) {
            return [
                'id'      => (int) $r->ID,
                'title'   => $r->post_title,
                'excerpt' => $r->post_excerpt,
                'date'    => $r->post_date,
                'status'  => $r->post_status,
            ];
        }, $records);

        self::respond ([
            'page'     => $page,
            'per_page' => $per_page,
            'total'    => Posts::totalPublished (),
            'data'     => $data,
        ]);
    }
}
