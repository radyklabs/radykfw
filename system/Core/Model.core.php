<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

// Load sibling Eloquent-lite classes (each in its own .core.php file)
require_once __DIR__ . '/QueryBuilder.core.php';
require_once __DIR__ . '/Record.core.php';
require_once __DIR__ . '/Relation.core.php';

/**
 * RadykFW Model — Eloquent-lite active-record + data-mapper layer.
 *
 * Design: thin wrappers over Database ($wpdb) with a fluent builder and
 * three relation helpers. No migrations, no eager-lazy N+1 cache, no
 * magic casts — stays lean and predictable on top of WP's fixed schema.
 *
 *     class Post extends Model
 *     {
 *         protected static string $table = 'posts';       // → wp_posts
 *         protected static string $key   = 'ID';
 *
 *         public static function author (): Relation
 *         {
 *             return self::belongsTo ('users', 'post_author');
 *         }
 *
 *         public static function comments (): Relation
 *         {
 *             return self::hasMany ('comments', 'comment_post_id');
 *         }
 *     }
 *
 * Usage:
 *     Post::where ('post_status', 'publish')->orderBy ('post_date','desc')->limit(5)->get()
 *     Post::find (123)->author              // lazy belongsTo
 *     Post::where ('ID', 123)->first
 *     Post::count
 *     Post::insert (['post_title' => 'Hi', 'post_status' => 'publish'])
 *
 * Sibling classes (each in its own file, auto-loaded above):
 *   - QueryBuilder (system/Core/QueryBuilder.core.php)
 *   - Record       (system/Core/Record.core.php)
 *   - Relation     (system/Core/Relation.core.php)
 *
 * @since 1.0.1
 */
abstract class Model
{
    /** Logical table name (auto-prefixed by Database). Subclasses set this. */
    protected static string $table = '';

    /** Primary-key column. Defaults to 'id'. */
    protected static string $key = 'id';

    // ──────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────

    /** Shared Database instance. */
    protected static function db (): Database
    {
        return Database::instance ();
    }

    /** Current module name (set by Router during dispatch). */
    protected static function module (): string
    {
        return \System\Core\Router::currentModule ();
    }

    /** Fully-qualified prefixed table name. */
    public static function tableName (): string
    {
        return self::db ()->prefix (static::$table);
    }

    // ──────────────────────────────────────────────────────────
    // Query builder entry points
    // ──────────────────────────────────────────────────────────

    /** Start a new query on this model. */
    public static function query (): QueryBuilder
    {
        return new QueryBuilder (static::db (), static::tableName (), static::class);
    }

    /**
     * Add a WHERE clause. Fluent.
     *
     * @param string   $column
     * @param mixed    $value  value
     * @param string   $op     operator (default '=')
     */
    public static function where (string $column, mixed $value = null, string $op = '='): QueryBuilder
    {
        return static::query ()->where ($column, $value, $op);
    }

    /** ORDER BY. Fluent. */
    public static function orderBy (string $column, string $direction = 'asc'): QueryBuilder
    {
        return static::query ()->orderBy ($column, $direction);
    }

    /** LIMIT. Fluent. */
    public static function limit (int $n): QueryBuilder
    {
        return static::query ()->limit ($n);
    }

    /** OFFSET. Fluent. */
    public static function offset (int $n): QueryBuilder
    {
        return static::query ()->offset ($n);
    }

    // ──────────────────────────────────────────────────────────
    // Terminal operations (return results, not builder)
    // ──────────────────────────────────────────────────────────

    /** Run the builder; returns array of Record instances. */
    public static function get (): array
    {
        return static::query ()->get ();
    }

    /** First matching row as a Record, or null. */
    public static function first (): ?Record
    {
        return static::query ()->first ();
    }

    /** Find by primary key. */
    public static function find (int|string $id): ?Record
    {
        $row = static::db ()->selectOne (
            \sprintf ('SELECT * FROM %s WHERE %s = ? LIMIT 1',
                static::tableName (), static::$key),
            [(int) $id]
        );
        return $row !== null ? new Record (static::class, $row) : null;
    }

    /** Existence check. */
    public static function exists (string $column, mixed $value = null, string $op = '='): bool
    {
        return static::where ($column, $value, $op)->exists ();
    }

    /** COUNT matching rows. */
    public static function count (): int
    {
        return static::query ()->count ();
    }

    // ──────────────────────────────────────────────────────────
    // Write helpers
    // ──────────────────────────────────────────────────────────

    /**
     * INSERT a row. Returns the last-inserted primary-key value (int).
     *
     * @param array $data column → value
     */
    public static function insert (array $data): int
    {
        global $wpdb;
        $wpdb->insert (static::tableName (), $data);
        return (int) $wpdb->insert_id;
    }

    /**
     * UPDATE rows by primary key. Returns affected rows.
     */
    public static function update (int|string $id, array $data): int
    {
        global $wpdb;
        $result = $wpdb->update (
            static::tableName (), $data, [static::$key => (int) $id]
        );
        return $result === false ? 0 : (int) $result;
    }

    /**
     * DELETE a row by primary key. Returns affected rows.
     */
    public static function delete (int|string $id): int
    {
        global $wpdb;
        $result = $wpdb->delete (
            static::tableName (), [static::$key => (int) $id]
        );
        return $result === false ? 0 : (int) $result;
    }

    // ──────────────────────────────────────────────────────────
    // Relations (Eloquent-lite)
    // ──────────────────────────────────────────────────────────

    /**
     * Declare a belongs-to relation.
     *
     * @param string $table      Related table (logical, auto-prefixed)
     * @param string $foreignKey FK column on THIS table pointing to related PK
     * @param string $ownerKey   PK column on the related table (default 'id')
     */
    public static function belongsTo (
        string $table,
        string $foreignKey,
        string $ownerKey = 'id'
    ): Relation {
        return new Relation (
            static::db (),
            $table,
            $foreignKey,   // FK on THIS table (source)
            $ownerKey,     // PK on RELATED table (lookup key)
            Relation::BELONGS_TO
        );
    }

    /**
     * Declare a has-many relation.
     *
     * @param string $table      Related table (logical, auto-prefixed)
     * @param string $foreignKey FK column on the RELATED table pointing to THIS table's PK
     */
    public static function hasMany (string $table, string $foreignKey): Relation
    {
        return new Relation (
            static::db (),
            $table,
            static::$key,   // source = THIS table's PK
            $foreignKey,    // lookup = RELATED table's FK column
            Relation::HAS_MANY
        );
    }

    /**
     * Declare a has-one relation.
     *
     * @param string $table      Related table (logical, auto-prefixed)
     * @param string $foreignKey FK column on the RELATED table pointing to THIS table's PK
     */
    public static function hasOne (string $table, string $foreignKey): Relation
    {
        return new Relation (
            static::db (),
            $table,
            static::$key,   // source = THIS table's PK
            $foreignKey,    // lookup = RELATED table's FK column
            Relation::HAS_ONE
        );
    }
}
