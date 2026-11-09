<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Relation — relation descriptor + lazy loader.
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
                'SELECT * FROM %s WHERE %s = %%d LIMIT 1',
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
                'SELECT * FROM %s WHERE %s = %%d',
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
                'SELECT * FROM %s WHERE %s = %%d LIMIT 1',
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
