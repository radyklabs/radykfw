<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Record;

/**
 * Model / Record / Relation — CRUD against the wpdb fake plus lazy
 * relation loading (first access queries, later accesses hit the cache).
 * Fixture: global CiPost (tests/support/model-fixtures.php).
 */
final class ModelRecordRelationTest extends TestCase
{
    private \wpdb $wpdb;

    protected function setUp (): void
    {
        $this->wpdb = $GLOBALS ['wpdb'];
        resetDatabaseBinding ();
    }

    // ── Model ──

    public function testTableNameResolvesPrefixedTable (): void
    {
        $this->assertSame ('wp_posts', \CiPost::tableName ());
    }

    public function testFindReturnsRecordOrNull (): void
    {
        $this->wpdb->queueRow (['ID' => 15, 'id' => 15, 'post_title' => 'Hi']);

        $record = \CiPost::find (15);

        $this->assertNotNull ($record);
        $this->assertSame ('Hi', $record->post_title);
        $this->assertSame (15, $record->toArray () ['ID'], 'Record::id() hardcodes key "id"');
        $this->assertSame ([15], end ($this->wpdb->prepared) ['args']);

        $this->wpdb->queueRow (null);
        $this->assertNull (\CiPost::find (999));
    }

    public function testInsertReturnsGeneratedInsertId (): void
    {
        $this->wpdb->insertQueue [] = 77;

        $id = \CiPost::insert (['post_title' => 'New']);

        $this->assertSame (77, $id);
        $this->assertSame (1, $this->wpdb->insertCalls);
        $this->assertSame ('wp_posts', $this->wpdb->lastInsert ['table']);
    }

    public function testUpdateAndDeleteReturnAffectedRows (): void
    {
        $this->assertSame (1, \CiPost::update (5, ['post_title' => 'X']));
        $this->assertSame (5, $this->wpdb->lastUpdate ['where'] ['ID']);

        $this->assertSame (1, \CiPost::delete (5));
        $this->assertSame (5, $this->wpdb->lastDelete ['where'] ['ID']);
    }

    public function testCountExistsAndFirstGoThroughBuilder (): void
    {
        $this->wpdb->queueVar ('3');
        $this->assertSame (3, \CiPost::count ());

        $this->wpdb->queueVar ('1');
        $this->assertTrue (\CiPost::exists ('post_status', 'publish'));

        $this->wpdb->queueResults ([['ID' => 8]]);
        $first = \CiPost::where ('post_status', 'publish')->first ();
        $this->assertNotNull ($first);
        $this->assertSame (8, $first->toArray () ['ID'], 'Record does not implement ArrayAccess; use toArray()');
    }

    // ── Record ──

    public function testRecordAttributeAndArrayAccess (): void
    {
        $record = new Record (\CiPost::class, ['ID' => 3, 'post_title' => 'T']);

        $this->assertSame ('T', $record->post_title);
        $this->assertSame ('T', $record->toArray () ['post_title']);
        $this->assertTrue (isset ($record->ID));
        $this->assertNull ($record->missing);

        $record->post_title = 'T2';
        $this->assertSame ('T2', $record->post_title);

        // Unset via magic: Record::__isset reads attributes; simulate by reading.
        $this->assertSame (3, $record->toArray () ['ID']);
        $this->assertSame (['ID' => 3, 'post_title' => 'T2'], $record->toArray ());
    }

    public function testRecordWithoutModelClassReturnsNullForRelations (): void
    {
        $record = new Record ('Does\\Not\\Exist', ['ID' => 1]);

        $this->assertNull ($record->author);
    }

    // ── Lazy relations ──

    public function testBelongsToIsLazyLoadedAndCached (): void
    {
        $this->wpdb->queueResults ([['ID' => 9, 'user_login' => 'ada']]);

        $record = new Record (\CiPost::class, ['ID' => 1, 'post_author' => 9]);

        $author = $record->author;
        $this->assertInstanceOf (Record::class, $author);
        $this->assertSame ('ada', $author->user_login);
        $this->assertCount (1, $this->wpdb->queries, 'first access must query once');
        $this->assertStringContainsString ('wp_users', end ($this->wpdb->queries));

        // Second access must hit the cache — no extra query.
        $again = $record->author;
        $this->assertSame ($author, $again);
        $this->assertCount (1, $this->wpdb->queries);
    }

    public function testHasManyReturnsRecordCollection (): void
    {
        $this->wpdb->queueResults ([
            ['comment_ID' => 1, 'comment_post_ID' => 5],
            ['comment_ID' => 2, 'comment_post_ID' => 5],
        ]);

        $record = new Record (\CiPost::class, ['ID' => 5]);
        $comments = $record->comments;

        $this->assertCount (2, $comments);
        $this->assertContainsOnlyInstancesOf (Record::class, $comments);
        $this->assertSame (1, $comments [0]->toArray () ['comment_ID']);
        $this->assertStringContainsString ('FROM wp_comments', end ($this->wpdb->queries));
    }

    public function testHasOneReturnsSingleRecordOrNull (): void
    {
        $this->wpdb->queueResults ([['meta_id' => 4, 'post_id' => 2]]);

        $record = new Record (\CiPost::class, ['ID' => 2]);
        $featured = $record->featured;

        $this->assertInstanceOf (Record::class, $featured);
        $this->assertSame (4, $featured->toArray () ['meta_id']);
        $this->assertStringContainsString ('LIMIT 1', end ($this->wpdb->queries));

        // Missing relation row → null.
        $this->wpdb->queueResults ([]);
        $empty = new Record (\CiPost::class, ['ID' => 3]);
        $this->assertNull ($empty->featured);
    }

    public function testRelationSkipsQueryWhenSourceKeyMissing (): void
    {
        $record = new Record (\CiPost::class, ['ID' => 1]); // no post_author

        $this->assertNull ($record->author);
        $this->assertSame ([], $this->wpdb->queries);
    }
}
