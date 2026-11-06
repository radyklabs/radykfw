<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW QueryBuilder — fluent query builder over Database.
 *
 * Chain: where(...)->orderBy(...)->limit(...)->get()
 *
 * Prepared values only — column names are trusted developer constants;
 * all user-supplied values are passed through ? placeholders to
 * $wpdb->prepare() (anti-injection).
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

    /** COUNT(*) with current WHERE context. */
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
