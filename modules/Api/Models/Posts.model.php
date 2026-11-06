<?php declare (strict_types=1);

namespace Modules\Api\Models;

use System\Core\Model;

/**
 * Api Posts model — example querying wp_posts.
 *
 * URL: /api/posts
 * File: Posts.model.php
 */
class Posts extends Model
{
    protected static string $table = 'wp_posts';

    /**
     * Get published posts with pagination.
     *
     * @param int $page    Page number (1-based)
     * @param int $perPage Items per page
     * @return array
     */
    public static function list (int $page = 1, int $perPage = 10): array
    {
        $page   = max (1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = \sprintf (
            'SELECT ID, post_title, post_excerpt, post_date, post_status '
            . 'FROM %s WHERE post_status = %%s '
            . 'ORDER BY post_date DESC LIMIT %d OFFSET %d',
            static::$table,
            $perPage,
            $offset
        );

        return self::db ()->select ($sql, ['publish']);
    }
}
