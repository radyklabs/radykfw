<?php declare (strict_types=1);

namespace Modules\Api\Models;

use System\Core\Model;

/**
 * Api Posts model — Eloquent-lite example.
 *
 * URL: /api/posts
 * File: Posts.model.php
 *
 * Table: posts (auto-prefixed → wp_posts)
 * Primary key: ID
 *
 * Relations:
 *   author() — belongsTo users via post_author
 */
class Posts extends Model
{
    protected static string $table = 'posts';
    protected static string $key   = 'ID';

    /**
     * Author relation: posts.post_author → users.ID
     */
    public static function author (): \System\Core\Relation
    {
        return self::belongsTo ('users', 'post_author');
    }

    /**
     * Get published posts with pagination.
     *
     * @param int $page    Page number (1-based)
     * @param int $perPage Items per page
     * @return \System\Core\Record[]
     */
    public static function list (int $page = 1, int $perPage = 10): array
    {
        $page   = max (1, $page);
        $offset = ($page - 1) * $perPage;

        return self::where ('post_status', 'publish')
            ->orderBy ('post_date', 'desc')
            ->limit ($perPage)
            ->offset ($offset)
            ->get ();
    }

    /**
     * Total count of published posts (for pagination meta).
     */
    public static function totalPublished (): int
    {
        return self::where ('post_status', 'publish')->count ();
    }
}
