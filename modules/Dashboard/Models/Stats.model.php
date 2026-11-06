<?php declare (strict_types=1);

namespace Modules\Dashboard\Models;

use System\Core\Model;

/**
 * Dashboard Stats model — Eloquent-lite example.
 *
 * URL: /dashboard/stats
 * File: Stats.model.php
 *
 * Table: posts (auto-prefixed → wp_posts)
 * Primary key: ID
 */
class Stats extends Model
{
    protected static string $table = 'posts';
    protected static string $key   = 'ID';

    /**
     * Count published posts.
     */
    public static function publishedCount (): int
    {
        return self::where ('post_status', 'publish')->count ();
    }

    /**
     * Count total posts.
     */
    public static function total (): int
    {
        return self::count ();
    }

    /**
     * Get the N most recent published posts.
     */
    public static function recent (int $limit = 5): array
    {
        return self::where ('post_status', 'publish')
            ->orderBy ('post_date', 'desc')
            ->limit ($limit)
            ->get ();
    }
}
