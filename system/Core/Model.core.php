<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Model — abstract base for module models.
 *
 * Extend from modules:
 *
 *     class Stats extends \System\Core\Model
 *     {
 *         protected static string $table = 'wp_posts';
 *         // ...
 *     }
 *
 * @since 1.0.1
 */
abstract class Model
{
    /**
     * Table name (with prefix, e.g. 'wp_posts').
     * Subclasses should set this.
     */
    protected static string $table = '';

    /**
     * Get the current module name (set by Router during dispatch).
     */
    protected static function module (): string
    {
        return \System\Core\Router::currentModule ();
    }

    /**
     * Get the shared Database instance.
     */
    protected static function db (): Database
    {
        return Database::instance ();
    }

    // ──────────────────────────────────────────────────────────
    // Thin CRUD helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Find a single row by primary key.
     *
     * @param int|string $id  Primary key value
     * @return array|null     Row as associative array, or null if not found
     */
    public static function find (int|string $id): ?array
    {
        $sql = \sprintf (
            'SELECT * FROM %s WHERE ID = %%d LIMIT 1',
            static::$table
        );
        $rows = self::db ()->select ($sql, [(int) $id]);
        return $rows !== [] ? $rows[0] : null;
    }

    /**
     * Find all rows, optionally filtered.
     *
     * @param array $where   Column → value pairs for WHERE
     * @param int   $limit   Max rows (0 = no limit)
     * @param int   $offset  Offset
     * @return array
     */
    public static function findAll (
        array $where = [],
        int $limit = 0,
        int $offset = 0
    ): array {
        $params = [];

        if ($where !== [])
        {
            $cols = [];
            foreach ($where as $col => $val)
            {
                $cols[] = $col . ' = ?';
                $params[] = $val;
            }
            $where_clause = ' WHERE ' . \implode (' AND ', $cols);
        }

        $sql = \sprintf ('SELECT * FROM %s%s', static::$table, $where_clause);

        if ($limit > 0)
        {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }

        if ($offset > 0)
        {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }

        return self::db ()->select ($sql, $params);
    }

    /**
     * Create a new row.
     *
     * @param array $data Column → value
     * @return int  Rows affected
     */
    public static function create (array $data): int
    {
        return self::db ()->insert (static::$table, $data);
    }

    /**
     * Update a row by primary key.
     *
     * @param int|string $id    Primary key value
     * @param array      $data  Column → value
     * @return int  Rows affected
     */
    public static function update (int|string $id, array $data): int
    {
        return self::db ()->update (
            static::$table,
            $data,
            ['ID' => (int) $id]
        );
    }

    /**
     * Delete a row by primary key.
     *
     * @param int|string $id Primary key value
     * @return int  Rows affected
     */
    public static function delete (int|string $id): int
    {
        return self::db ()->delete (static::$table, ['ID' => (int) $id]);
    }
}
