<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Database;
use System\Core\QueryBuilder;

/**
 * Database wrapper + QueryBuilder against the in-memory wpdb fake.
 * Values must reach wpdb->prepare() as separate arguments (anti-injection).
 */
final class DatabaseQueryBuilderTest extends TestCase
{
    private \wpdb $wpdb;

    protected function setUp (): void
    {
        $this->wpdb = $GLOBALS ['wpdb'];
        resetDatabaseBinding ();
    }

    public function testPrefixResolvesCoreTablesFromWpdbProperties (): void
    {
        $db = Database::instance ();

        $this->assertSame ('wp_posts', $db->prefix ('posts'));
        $this->assertSame ('wp_users', $db->prefix ('users'));
    }

    public function testPrefixAddsSeparatorForCustomTables (): void
    {
        $db = Database::instance ();

        $this->assertSame ('wp_radykfw_settings', $db->prefix ('radykfw_settings'));
    }

    public function testPrefixIsIdempotent (): void
    {
        $db = Database::instance ();

        $this->assertSame ('wp_radykfw_settings', $db->prefix ('wp_radykfw_settings'));
    }

    public function testSelectPassesValuesThroughPrepare (): void
    {
        $db = Database::instance ();
        $this->wpdb->queueResults ([['id' => 1]]);

        $rows = $db->select ('SELECT * FROM wp_posts WHERE post_status = %s', ['publish']);

        $this->assertSame ([['id' => 1]], $rows);
        $this->assertCount (1, $this->wpdb->prepared);
        $this->assertSame (['publish'], $this->wpdb->prepared [0] ['args']);
        // Single-value call must use the varargs form, never a positional array.
        $this->assertStringContainsString ('post_status = publish', end ($this->wpdb->queries));
    }

    public function testSelectWithoutValuesSkipsPrepare (): void
    {
        $db = Database::instance ();
        $this->wpdb->queueResults ([['id' => 2]]);

        $rows = $db->select ('SELECT * FROM wp_posts');

        $this->assertSame ([['id' => 2]], $rows);
        $this->assertSame ([], $this->wpdb->prepared);
    }

    public function testSelectOneReturnsNullWhenNoRow (): void
    {
        $db = Database::instance ();
        $this->wpdb->queueRow (null);

        $this->assertNull ($db->selectOne ('SELECT * FROM wp_posts WHERE ID = %d', [1]));
    }

    public function testSelectOneCastsRowToArray (): void
    {
        $db = Database::instance ();
        $this->wpdb->queueRow (['ID' => 3]);

        $this->assertSame (['ID' => 3], $db->selectOne ('SELECT * FROM wp_posts WHERE ID = %d', [3]));
    }

    public function testCountCastsScalarToInteger (): void
    {
        $db = Database::instance ();
        $this->wpdb->queueVar ('7');

        $this->assertSame (7, $db->count ('SELECT COUNT(*) FROM wp_posts'));
    }

    public function testInsertUpdateDeleteReturnAffectedRows (): void
    {
        $db = Database::instance ();

        $this->assertSame (1, $db->insert ('radykfw_settings', ['k' => 'v']));
        $this->assertSame ('wp_radykfw_settings', $this->wpdb->lastInsert ['table']);

        $this->assertSame (1, $db->update ('radykfw_settings', ['k' => 'v2'], ['k' => 'v']));
        $this->assertSame ('wp_radykfw_settings', $this->wpdb->lastUpdate ['table']);

        $this->assertSame (1, $db->delete ('radykfw_settings', ['k' => 'v2']));
        $this->assertSame ('wp_radykfw_settings', $this->wpdb->lastDelete ['table']);
    }

    public function testWriteFailuresMapToZeroAffectedRows (): void
    {
        $db = Database::instance ();
        $this->wpdb->failWrites = true;

        $this->assertSame (0, $db->insert ('radykfw_settings', ['k' => 'v']));
        $this->assertSame (0, $db->update ('radykfw_settings', ['k' => 'v'], ['k' => 'v']));
        $this->assertSame (0, $db->delete ('radykfw_settings', ['k' => 'v']));
    }

    public function testTransactionCommitsOnSuccess (): void
    {
        $db = Database::instance ();

        $result = $db->transaction (static fn (Database $db): string => 'ok');

        $this->assertSame ('ok', $result);
        $this->assertContains ('START TRANSACTION', $this->wpdb->queries);
        $this->assertContains ('COMMIT', $this->wpdb->queries);
        $this->assertNotContains ('ROLLBACK', $this->wpdb->queries);
    }

    public function testTransactionRollsBackAndRethrowsOnFailure (): void
    {
        $db = Database::instance ();

        try
        {
            $db->transaction (static function (): void {
                throw new \RuntimeException ('boom');
            });
            $this->fail ('transaction must rethrow');
        }
        catch (\RuntimeException $e)
        {
            $this->assertSame ('boom', $e->getMessage ());
        }

        $this->assertContains ('ROLLBACK', $this->wpdb->queries);
        $this->assertNotContains ('COMMIT', $this->wpdb->queries);
    }

    // ── QueryBuilder ──

    public function testQueryBuilderCompilesWhereOrderLimitOffset (): void
    {
        $this->wpdb->queueResults ([['id' => 1]]);

        (new QueryBuilder (Database::instance (), 'wp_posts'))
            ->select ('ID', 'post_title')
            ->where ('post_status', 'publish')
            ->where ('post_type', 'page', '!=')
            ->orderBy ('post_date', 'desc')
            ->limit (10)
            ->offset (5)
            ->get ();

        $lastQuery = end ($this->wpdb->queries);
        $this->assertStringContainsString ('SELECT ID, post_title FROM wp_posts', $lastQuery);
        $this->assertStringContainsString ('WHERE post_status = publish AND post_type != page', $lastQuery);
        $this->assertStringContainsString ('ORDER BY post_date DESC', $lastQuery);
        $this->assertStringContainsString ('LIMIT 10', $lastQuery);
        $this->assertStringContainsString ('OFFSET 5', $lastQuery);
    }

    public function testQueryBuilderOrWhereJoinsWithOr (): void
    {
        $this->wpdb->queueResults ([]);

        (new QueryBuilder (Database::instance (), 'wp_posts'))
            ->where ('a', 1)
            ->orWhere ('b', 2)
            ->get ();

        $sql = end ($this->wpdb->prepared) ['sql'];
        $this->assertStringContainsString ('WHERE a = %s OR b = %s', $sql);
        $this->assertSame ([1, 2], end ($this->wpdb->prepared) ['args']);
    }

    public function testFirstLimitsToOneAndReturnsNullWhenEmpty (): void
    {
        $this->wpdb->queueResults ([['id' => 1]]);

        $builder = new QueryBuilder (Database::instance (), 'wp_posts');
        $first = $builder->first ();
        $this->assertNotNull ($first);
        $this->assertStringContainsString ('LIMIT 1', end ($this->wpdb->queries));

        $this->wpdb->queueResults ([]);
        $this->assertNull ((new QueryBuilder (Database::instance (), 'wp_posts'))->first ());
    }

    public function testCountAndExists (): void
    {
        $this->wpdb->queueVar ('4');
        $count = (new QueryBuilder (Database::instance (), 'wp_posts'))
            ->where ('post_status', 'publish')
            ->count ();
        $this->assertSame (4, $count);
        $this->assertStringContainsString ('SELECT COUNT(*) AS c FROM wp_posts WHERE post_status = %s', end ($this->wpdb->prepared) ['sql']);

        $this->wpdb->queueVar ('1');
        $this->assertTrue ((new QueryBuilder (Database::instance (), 'wp_posts'))->exists ());

        $this->wpdb->queueVar (null);
        $this->assertFalse ((new QueryBuilder (Database::instance (), 'wp_posts'))->exists ());
    }

    public function testGetWrapsRowsInRecords (): void
    {
        $this->wpdb->queueResults ([['id' => 1], ['id' => 2]]);

        $records = (new QueryBuilder (Database::instance (), 'wp_posts'))->get ();

        $this->assertCount (2, $records);
        $this->assertContainsOnlyInstancesOf (\System\Core\Record::class, $records);
        $this->assertSame (1, $records [0]->id ());
    }
}
