<?php declare (strict_types=1);

/**
 * Shared fixtures + reset helper for Model-layer tests.
 *
 * Database is a singleton that captures $GLOBALS['wpdb'] at first
 * construction, so tests share one instance and must reset both the
 * fake and the singleton between cases (resetDatabaseBinding() below).
 */

require_once dirname (__DIR__, 2) . '/system/Core/Model.core.php';
require_once dirname (__DIR__, 2) . '/system/Core/Database.core.php';

class CiPost extends \System\Core\Model
{
    protected static string $table = 'posts';
    protected static string $key = 'ID';

    public static function author (): \System\Core\Relation
    {
        return self::belongsTo ('users', 'post_author', 'ID');
    }

    public static function comments (): \System\Core\Relation
    {
        return self::hasMany ('comments', 'ID');
    }

    public static function featured (): \System\Core\Relation
    {
        return self::hasOne ('postmeta', 'ID');
    }
}

function resetDatabaseBinding (): void
{
    $reflection = new \ReflectionClass (\System\Core\Database::class);
    $reflection->getProperty ('instance')->setValue (null, null);

    $GLOBALS ['wpdb']->reset ();
    $GLOBALS ['wpdb']->insert_id = 0;
}
