<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Database — re-branded wrapper over $wpdb (Eloquent-lite layer).
 *
 * The Model layer (Model / QueryBuilder / Relation / Record) consumes this.
 * Values are ALWAYS passed as separate placeholders to $wpdb->prepare() —
 * nothing is ever concatenated into a query string (anti-injection).
 *
 * Table names: pass the logical name (e.g. 'posts'); prefix() resolves it
 * to 'wp_posts' via $wpdb->prefix. Names that already carry the prefix
 * are returned as-is.
 *
 * No full ORM / migration system — stays close to SQL for control.
 *
 * @since 1.0.1
 */
final class Database
{
    /** Singleton instance. */
    private static ?self $instance = null;

    /** @var \wpdb */
    private \wpdb $db;

    private function __construct ()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get the singleton Database instance.
     */
    public static function instance (): self
    {
        if (is (self::$instance, null))
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ──────────────────────────────────────────────────────────
    // Prefix / escape-hatch
    // ──────────────────────────────────────────────────────────

    /**
     * Resolve a logical table name to its prefixed form.
     *
     * 'posts'    → 'wp_posts'
     * 'wp_posts' → 'wp_posts' (already prefixed)
     */
    public function prefix (string $table): string
    {
        return str_starts_with ($table, $this->db->prefix . '_')
            ? $table
            : $this->db->prefix . '_' . $table;
    }

    /**
     * Get the raw \wpdb instance (escape hatch).
     */
    public function wpdb (): \wpdb
    {
        return $this->db;
    }

    // ──────────────────────────────────────────────────────────
    // SELECT (prepared values only — no string concat)
    // ──────────────────────────────────────────────────────────

    /**
     * SELECT — returns array of rows (ARRAY_A).
     *
     * @param string $sql    SQL with ? placeholders
     * @param array  $values Positional values matching ? order
     * @return array
     */
    public function select (string $sql, array $values = []): array
    {
        $sql = $this->prepared ($sql, $values);
        return $this->db->get_results ($sql, ARRAY_A) ?? [];
    }

    /**
     * SELECT single row — first row or null.
     *
     * @return array|null
     */
    public function selectOne (string $sql, array $values = []): ?array
    {
        $sql = $this->prepared ($sql, $values);
        $row = $this->db->get_row ($sql, ARRAY_A);
        return is_null ($row) ? null : (array) $row;
    }

    /**
     * SELECT single scalar (first column of first row) or null.
     */
    public function selectValue (string $sql, array $values = []): mixed
    {
        $sql = $this->prepared ($sql, $values);
        return $this->db->get_var ($sql);
    }

    /**
     * Count rows for a prepared query.
     */
    public function count (string $sql, array $values = []): int
    {
        return (int) $this->selectValue ($sql, $values);
    }

    // ──────────────────────────────────────────────────────────
    // Write operations (auto-prefix, wpdb-safe)
    // ──────────────────────────────────────────────────────────

    /**
     * INSERT — affected rows (0 on failure).
     *
     * @param string $table Logical table name (auto-prefixed)
     * @param array  $data  Column → value
     */
    public function insert (string $table, array $data): int
    {
        $result = $this->db->insert ($this->prefix ($table), $data);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * UPDATE — affected rows (0 on failure).
     *
     * @param string $table  Logical table name (auto-prefixed if not already)
     * @param array  $data   Column → value
     * @param array  $where  Column → value (WHERE)
     */
    public function update (string $table, array $data, array $where = []): int
    {
        $result = $this->db->update ($this->prefix ($table), $data, $where);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * DELETE — affected rows (0 on failure).
     *
     * @param string $table Logical table name (auto-prefixed if not already)
     * @param array  $where Column → value (WHERE)
     */
    public function delete (string $table, array $where): int
    {
        $result = $this->db->delete ($this->prefix ($table), $where);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * Raw query (DDL / custom DML) with prepared values.
     */
    public function execute (string $sql, array $values = []): int
    {
        $sql = $this->prepared ($sql, $values);
        $result = $this->db->query ($sql);
        return $result === false ? 0 : (int) $result;
    }

    // ──────────────────────────────────────────────────────────
    // Transaction
    // ──────────────────────────────────────────────────────────

    /**
     * Run a callable inside a transaction; commit on success, roll back
     * on any \Throwable. Returns the callable's result.
     */
    public function transaction (callable $fn): mixed
    {
        $this->db->query ('START TRANSACTION');
        try
        {
            $result = $fn ($this);
            $this->db->query ('COMMIT');
            return $result;
        }
        catch (\Throwable $e)
        {
            $this->db->query ('ROLLBACK');
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────

    /**
     * Prepare a SQL string with ? placeholders via $wpdb->prepare().
     * No-op when $values is empty (returns the SQL as-is).
     */
    private function prepared (string $sql, array $values = []): string
    {
        if ($values === []) {return $sql;}
        return $this->db->prepare ($sql, $values);
    }
}
