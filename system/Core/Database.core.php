<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Database — re-branded wrapper over $wpdb.
 *
 * Provides a clean API: select(), insert(), update(), delete(),
 * transaction(). No full ORM — stays close to SQL for performance & control.
 *
 * @since 1.0.1
 */
final class Database
{
    /** Singleton instance. */
    private static ?self $instance = null;

    /** @var \wpdb */
    private \wpdb $db;

    /**
     * Private constructor — use Database::instance().
     */
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
    // CRUD
    // ──────────────────────────────────────────────────────────

    /**
     * SELECT — returns array of rows (ARRAY_A).
     *
     * @param string $sql     SQL with ? placeholders
     * @param array  $params  Values to prepare (positional, matches ? order)
     * @return array
     */
    public function select (string $sql, array $params = []): array
    {
        if ($params !== [])
        {
            $sql = $this->db->prepare ($sql, ...$params);
        }
        return $this->db->get_results ($sql, ARRAY_A) ?? [];
    }

    /**
     * INSERT — returns number of affected rows.
     *
     * @param string $table  Table name
     * @param array  $data   Column → value
     * @param array  $format Optional column → format map
     * @return int
     */
    public function insert (string $table, array $data, ?array $format = null): int
    {
        $result = $this->db->insert ($table, $data, $format);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * UPDATE — returns number of affected rows.
     *
     * @param string $table  Table name
     * @param array  $data   Column → value
     * @param array  $where  Column → value (WHERE clause)
     * @param array  $format Optional column → format map for $data
     * @param array  $where_format Optional column → format map for $where
     * @return int
     */
    public function update (
        string $table,
        array $data,
        array $where,
        ?array $format = null,
        ?array $where_format = null
    ): int {
        $result = $this->db->update ($table, $data, $where, $format, $where_format);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * DELETE — returns number of affected rows.
     *
     * @param string $table  Table name
     * @param array  $where  Column → value (WHERE clause)
     * @return int
     */
    public function delete (string $table, array $where): int
    {
        $result = $this->db->delete ($table, $where);
        return $result === false ? 0 : (int) $result;
    }

    /**
     * Raw query with prepared params — returns number of affected rows.
     *
     * @param string $sql     SQL with placeholders
     * @param array  $params  Values to prepare
     * @return int
     */
    public function execute (string $sql, array $params = []): int
    {
        if ($params !== [])
        {
            $sql = $this->db->prepare ($sql, $params);
        }
        $result = $this->db->query ($sql);
        return $result === false ? 0 : (int) $result;
    }

    // ──────────────────────────────────────────────────────────
    // Transaction
    // ──────────────────────────────────────────────────────────

    /**
     * Run a callback inside a transaction.
     * Rolls back on exception.
     *
     * @param \Closure $fn
     * @return mixed Result of $fn
     */
    public function transaction (callable $fn): mixed
    {
        $this->db->query ('BEGIN');

        try {
            $result = $fn ($this);
            $this->db->query ('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $this->db->query ('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Get the raw \wpdb instance (escape hatch).
     */
    public function wpdb (): \wpdb
    {
        return $this->db;
    }
}
