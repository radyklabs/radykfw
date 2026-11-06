<?php declare (strict_types=1);

namespace Modules\Dashboard\Models;

use System\Core\Model;

/**
 * Dashboard Stats model — example using Database.
 *
 * URL: /dashboard/stats
 * File: Stats.model.php
 */
class Stats extends Model
{
    protected static string $table = 'wp_posts';

    /**
     * Count published posts.
     */
    public static function publishedCount (): int
    {
        $sql = \sprintf (
            'SELECT COUNT(*) AS cnt FROM %s WHERE post_status = %%s',
            static::$table
        );
        $rows = self::db ()->select ($sql, ['publish']);
        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Count total posts.
     */
    public static function total (): int
    {
        $sql = \sprintf ('SELECT COUNT(*) AS cnt FROM %s', static::$table);
        $rows = self::db ()->select ($sql);
        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Get the N most recent published posts.
     */
    public static function recent (int $limit = 5): array
    {
        $sql = \sprintf (
            'SELECT * FROM %s WHERE post_status = %%s ORDER BY post_date DESC LIMIT %d',
            static::$table,
            $limit
        );
        return self::db ()->select ($sql, ['publish']);
    }
}
