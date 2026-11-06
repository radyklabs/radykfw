<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

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
     * @param mixed    $value  (or operator)
     * @param string   $op     optional operator (default '=')
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
     * @param string $table     Related table (logical, auto-prefixed)
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

// ─────────────────────────────────────────────────────────────
// QueryBuilder
// ─────────────────────────────────────────────────────────────

/**
 * Fluent query builder over Database (prepared values only).
 *
 * Chain: where(...)->orderBy(...)->limit(...)->get()
 *
 * @since 1.0.1
 */
class QueryBuilder
{
    private Database $db;
    private string $table;
    private string $owner;
    private array $wheres = [];
    private array $orders = [];
    private int $limitVal = 0;
    private int $offsetVal = 0;
    private ?string $select = null;

    public function __construct (Database $db, string $table, string $owner = '')
    {
        $this->db = $db;
        $this->table = $table;
        $this->owner = $owner;
    }

    /** WHERE col op value — fluent. */
    public function where (string $col, mixed $value = null, string $op = '='): self
    {
        $this->wheres[] = [$col, $op, $value];
        return $this;
    }

    /** OR WHERE — best-effort; AND is the primary join. */
    public function orWhere (string $col, mixed $value = null, string $op = '='): self
    {
        $this->wheres[] = ['__or__'];
        $this->wheres[] = [$col, $op, $value];
        return $this;
    }

    /** ORDER BY col dir */
    public function orderBy (string $col, string $dir = 'asc'): self
    {
        $this->orders[] = [$col, strtoupper ($dir) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    /** LIMIT n */
    public function limit (int $n): self
    {
        $this->limitVal = max (0, $n);
        return $this;
    }

    /** OFFSET n */
    public function offset (int $n): self
    {
        $this->offsetVal = max (0, $n);
        return $this;
    }

    /** SELECT specific columns instead of * */
    public function select (string ...$cols): self
    {
        $this->select = implode (', ', $cols);
        return $this;
    }

    // ── Terminal ──

    /** Run; returns array of Record instances. */
    public function get (): array
    {
        [$sql, $values] = $this->compile ();
        $rows = $this->db->select ($sql, $values);
        $records = [];
        foreach ($rows as $row)
        {
            $records[] = new Record ($this->owner, $row);
        }
        return $records;
    }

    /** First matching row as Record, or null. */
    public function first (): ?Record
    {
        $this->limitVal = 1;
        $results = $this->get ();
        return $results !== [] ? $results[0] : null;
    }

    /** COUNT(*) with current WHERE/ORDER context. */
    public function count (): int
    {
        $where_sql = $this->compileWhere ();
        $sql = \sprintf ('SELECT COUNT(*) AS c FROM %s%s', $this->table, $where_sql);
        $values = $this->collectValues ();
        return $this->db->count ($sql, $values);
    }

    /** EXISTS check. */
    public function exists (): bool
    {
        $where_sql = $this->compileWhere ();
        $sql = \sprintf ('SELECT 1 FROM %s%s LIMIT 1', $this->table, $where_sql);
        $values = $this->collectValues ();
        return $this->db->selectValue ($sql, $values) !== null;
    }

    // ── Compile ──

    /**
     * @return array{0: string, 1: array} [sql, values]
     */
    private function compile (): array
    {
        $select_cols = $this->select ?? '*';
        $where_sql = $this->compileWhere ();

        $sql = \sprintf ('SELECT %s FROM %s%s', $select_cols, $this->table, $where_sql);

        $orders = '';
        if ($this->orders !== [])
        {
            $parts = [];
            foreach ($this->orders as [$col, $dir])
            {
                $parts[] = $col . ' ' . $dir;
            }
            $orders = ' ORDER BY ' . implode (', ', $parts);
        }
        $sql .= $orders;

        if ($this->limitVal > 0)
        {
            $sql .= \sprintf (' LIMIT %d', $this->limitVal);
        }
        if ($this->offsetVal > 0)
        {
            $sql .= \sprintf (' OFFSET %d', $this->offsetVal);
        }

        return [$sql, $this->collectValues ()];
    }

    private function compileWhere (): string
    {
        // Flatten: $this->wheres is [ [col,op,val], ['__or__'], [col,op,val], ... ]
        if ($this->wheres === []) {return '';}
        $parts = [];
        $join  = 'AND';
        foreach ($this->wheres as $w)
        {
            if (is_array ($w) && ($w[0] ?? null) === '__or__')
            {
                $join = 'OR';
                continue;
            }
            $parts[] = $join . ' ' . $w[0] . ' ' . $w[1] . ' ?';
            $join = 'AND';
        }
        return $parts !== [] ? ' WHERE ' . implode (' ', $parts) : '';
    }

    /**
     * Collect prepared ? values in clause order.
     */
    private function collectValues (): array
    {
        $values = [];
        foreach ($this->wheres as $w)
        {
            if (is_array ($w) && ($w[0] ?? null) === '__or__') {continue;}
            $values[] = $w[2];
        }
        return $values;
    }
}

// ─────────────────────────────────────────────────────────────
// Record — single-row wrapper with lazy relation access
// ─────────────────────────────────────────────────────────────

/**
 * A single DB row wrapped as an object. Attribute access via
 * $record->ID or $record ['ID']. Magic property access for
 * declared relations (lazy-loaded on first access).
 *
 * @since 1.0.1
 */
class Record
{
    private ?\ReflectionClass $modelClass;
    private array $attributes;
    private string $key;
    /** @var array<string, mixed> */
    private array $relations = [];

    /**
     * @param \System\Core\Model|class-string<Model> $modelClass
     * @param array  $attributes row data
     * @param string $key        primary-key column name
     */
    public function __construct (
        string $modelClass,
        array $attributes
    ) {
        $this->modelClass = class_exists ($modelClass)
            ? new \ReflectionClass ($modelClass)
            : null;
        $this->attributes = $attributes;
        $this->key = 'id';
    }

    /**
     * Magic access: first try known attribute; then try declared
     * static relation methods on the owner Model.
     */
    public function __get (string $prop)
    {
        if (array_key_exists ($prop, $this->attributes))
        {
            return $this->attributes[$prop];
        }

        $method = $this->resolveRelationMethod ($prop);
        if ($method !== null)
        {
            return $this->loadRelation ($method);
        }

        return null;
    }

    public function __set (string $prop, mixed $value): void
    {
        $this->attributes[$prop] = $value;
    }

    public function __isset (string $prop): bool
    {
        return array_key_exists ($prop, $this->attributes);
    }

    /** Array access. */
    public function __get_arr ()
    {
        return $this->attributes;
    }

    public function &offsetGet (string $offset): mixed
    {
        return $this->attributes[$offset];
    }

    public function offsetSet (string $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetExists (string $offset): bool
    {
        return array_key_exists ($offset, $this->attributes);
    }

    public function offsetUnset (string $offset): void
    {
        unset ($this->attributes[$offset]);
    }

    /** Cast to plain associative array. */
    public function toArray (): array
    {
        return $this->attributes;
    }

    /** Primary key value. */
    public function id (): int
    {
        return (int) ($this->attributes[$this->key] ?? 0);
    }

    // ── Relation loading ──

    private function resolveRelationMethod (string $prop): ?string
    {
        if ($this->modelClass === null) {return null;}
        if ($this->modelClass->hasMethod ($prop))
        {
            $method = $this->modelClass->getMethod ($prop);
            if ($method->isStatic ()
                && $method->getDeclaringClass ()->getName () !== Model::class)
            {
                return $prop;
            }
        }
        return null;
    }

    private function loadRelation (string $method)
    {
        if (array_key_exists ($method, $this->relations))
        {
            return $this->relations[$method];
        }

        if ($this->modelClass === null) {return null;}

        $reflection = $this->modelClass->getMethod ($method);
        $relation = $reflection->invoke (null);

        if ($relation instanceof Relation)
        {
            $result = $this->executeRelation ($relation);
            $this->relations[$method] = $result;
            return $result;
        }

        $this->relations[$method] = null;
        return null;
    }

    /**
     * Execute a Relation given this Record's primary-key value.
     */
    private function executeRelation (Relation $rel): mixed
    {
        $pk = $this->attributes[$rel->sourceKey ()] ?? null;
        if ($pk === null) {return null;}

        switch ($rel->type ())
        {
            case Relation::HAS_ONE:
                return $rel->recordForOne ($pk);
            case Relation::HAS_MANY:
                return $rel->recordsForMany ($pk);
            case Relation::BELONGS_TO:
                return $rel->recordForBelongsTo ($pk);
        }
        return null;
    }
}

// ─────────────────────────────────────────────────────────────
// Relation — relation descriptor + lazy loader
// ─────────────────────────────────────────────────────────────

/**
 * Describes a relation between two tables.
 *
 * Relation types:
 *   BELONGS_TO — this table has FK pointing to the related table's PK
 *   HAS_MANY   — related table has FK pointing to this table's PK
 *   HAS_ONE    — like HAS_MANY but returns at most one record
 *
 * @since 1.0.1
 */
class Relation
{
    public const BELONGS_TO = 'belongs_to';
    public const HAS_MANY   = 'has_many';
    public const HAS_ONE    = 'has_one';

    private Database $db;
    private string $table;
    private string $foreignKey;
    private string $ownerKey;
    private string $type;

    public function __construct (
        Database $db,
        string $table,
        string $foreignKey,
        string $ownerKey,
        string $type
    ) {
        $this->db         = $db;
        $this->table      = $table;
        $this->foreignKey = $foreignKey;
        $this->ownerKey   = $ownerKey;
        $this->type       = $type;
    }

    /**
     * The source column on the OWNING table.
     * BELONGS_TO: the FK on this table ($this->foreignKey).
     * HAS_MANY/HAS_ONE: this table's primary key ($this->ownerKey).
     */
    public function sourceKey (): string
    {
        return $this->type === self::BELONGS_TO
            ? $this->foreignKey
            : $this->ownerKey;
    }

    /** Relation type constant. */
    public function type (): string
    {
        return $this->type;
    }

    /**
     * For HAS_ONE: fetch a single related Record.
     */
    public function recordForOne (int|string $sourcePk): ?Record
    {
        $rows = $this->db->select (
            \sprintf (
                'SELECT * FROM %s WHERE %s = ? LIMIT 1',
                $this->db->prefix ($this->table),
                $this->foreignKey
            ),
            [(int) $sourcePk]
        );
        return $rows !== []
            ? new Record (self::modelClassForTable ($this->table), $rows[0])
            : null;
    }

    /**
     * For HAS_MANY: fetch all related Records.
     */
    public function recordsForMany (int|string $sourcePk): array
    {
        $rows = $this->db->select (
            \sprintf (
                'SELECT * FROM %s WHERE %s = ?',
                $this->db->prefix ($this->table),
                $this->foreignKey
            ),
            [(int) $sourcePk]
        );
        $records = [];
        foreach ($rows as $row)
        {
            $records[] = new Record (
                self::modelClassForTable ($this->table),
                $row
            );
        }
        return $records;
    }

    /**
     * For BELONGS_TO: fetch the owner Record.
     * The FK column on THIS table ($this->foreignKey) holds the related
     * table's PK ($this->ownerKey).
     */
    public function recordForBelongsTo (int|string $fkValue): ?Record
    {
        $rows = $this->db->select (
            \sprintf (
                'SELECT * FROM %s WHERE %s = ? LIMIT 1',
                $this->db->prefix ($this->table),
                $this->ownerKey
            ),
            [(int) $fkValue]
        );
        return $rows !== []
            ? new Record (self::modelClassForTable ($this->table), $rows[0])
            : null;
    }

    /**
     * Best-effort model class resolution for a table name.
     * Looks up Modules\{Module}\Models\{PascalCase(table)} if it exists;
     * falls back to the bare table name as the Record owner string.
     */
    private static function modelClassForTable (string $table): string
    {
        // Best-effort: if Router is available (WP context), look up
        // Modules\{Module}\Models\{PascalCase(table)}; otherwise fall back
        // to the bare table string (Record handles string gracefully).
        if (class_exists (\System\Core\Router::class, false))
        {
            $module = \System\Core\Router::currentModule ();
            $class_name = ucfirst (\str_replace ('_', '', $table));
            $candidate = 'Modules\\' . $module . '\\Models\\' . $class_name;
            if (class_exists ($candidate)) {return $candidate;}
        }
        return $table;
    }
}
