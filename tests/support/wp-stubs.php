<?php declare (strict_types=1);

/**
 * Shared WordPress runtime stubs + fakes for the pure-PHP test suite.
 *
 * Loaded from tests/bootstrap.php before any test runs. Every symbol is
 * guarded with function_exists/class_exists so a real WP runtime (or an
 * earlier definition) always wins. Behaviour is driven via $GLOBALS so
 * individual tests can override responses without redeclaration.
 */

require_once __DIR__ . '/../wp-constants.stub.php';

if (!class_exists ('WP'))
{
    /** Minimal WP request object for Router type hints. */
    class WP
    {
        public string $request = '';
        public array $query_vars = [];
    }
}

if (!class_exists ('wpdb'))
{
    /**
     * In-memory wpdb fake: records queries, replays queued results.
     * Database::instance() captures the global once, so tests share this
     * single mutable instance (reset() in setUp).
     */
    class wpdb
    {
        /** @var array<int, array> */
        public array $insertQueue = [];
        public string $prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $comments = 'wp_comments';
        public string $commentmeta = 'wp_commentmeta';
        public string $links = 'wp_links';
        public string $users = 'wp_users';
        public string $usermeta = 'wp_usermeta';
        public string $terms = 'wp_terms';
        public string $term_relationships = 'wp_term_relationships';
        public string $term_taxonomy = 'wp_term_taxonomy';
        public string $termmeta = 'wp_termmeta';
        public int $insert_id = 0;

        /** @var array<int, array{sql: string, args: array}> */
        public array $prepared = [];
        /** @var string[] Every SQL string passed to query()/get_*. */
        public array $queries = [];
        public int $insertCalls = 0;
        public int $updateCalls = 0;
        public int $deleteCalls = 0;
        public ?array $lastInsert = null;
        public ?array $lastUpdate = null;
        public ?array $lastDelete = null;
        public bool $failWrites = false;

        /** @var array<int, array> Rows replayed by get_results(). */
        private array $resultQueue = [];
        /** @var array<int, mixed> Scalars replayed by get_var(). */
        private array $varQueue = [];
        private ?array $row = null;

        public function reset (): void
        {
            $this->prepared = [];
            $this->queries = [];
            $this->insertCalls = 0;
            $this->updateCalls = 0;
            $this->deleteCalls = 0;
            $this->lastInsert = null;
            $this->lastUpdate = null;
            $this->lastDelete = null;
            $this->failWrites = false;
            $this->insert_id = 0;
            $this->resultQueue = [];
            $this->varQueue = [];
            $this->row = null;
            $this->insertQueue = [];
        }

        public function queueResults (array $rows): void
        {
            $this->resultQueue [] = $rows;
        }

        public function queueVar (mixed $value): void
        {
            $this->varQueue [] = $value;
        }

        public function queueRow (?array $row): void
        {
            $this->row = $row;
        }

        public function prepare (string $query, ...$args): string
        {
            if (count ($args) === 1 && is_array ($args [0])) {$args = $args [0];}
            $this->prepared [] = ['sql' => $query, 'args' => $args];
            $i = 0;
            return preg_replace_callback (
                '/%[sdf]/',
                static function (array $m) use (&$i, $args): string {
                    $value = $args [$i++] ?? '';
                    return $m [0] === '%d' ? (string) (int) $value : (string) $value;
                },
                $query
            );
        }

        public function get_results (string $query, string $output = 'OBJECT'): array
        {
            $this->queries [] = $query;
            return array_shift ($this->resultQueue) ?? [];
        }

        public function get_row (string $query, string $output = 'OBJECT'): ?array
        {
            $this->queries [] = $query;
            return $this->row;
        }

        public function get_var (string $query): mixed
        {
            $this->queries [] = $query;
            return array_shift ($this->varQueue);
        }

        public function query (string $query): bool|int
        {
            $this->queries [] = $query;
            return true;
        }

        public function insert (string $table, array $data, ?array $format = null): int|false
        {
            $this->insertCalls++;
            $this->lastInsert = ['table' => $table, 'data' => $data];
            if ($this->failWrites) {return false;}
            $this->insert_id = $this->insertQueue ? array_shift ($this->insertQueue) : 42;
            return 1;
        }

        public function update (string $table, array $data, array $where, array $format = null, array $where_format = null): bool|int
        {
            $this->updateCalls++;
            $this->lastUpdate = ['table' => $table, 'data' => $data, 'where' => $where];
            return $this->failWrites ? false : 1;
        }

        public function delete (string $table, array $where, array $format = null): bool|int
        {
            $this->deleteCalls++;
            $this->lastDelete = ['table' => $table, 'where' => $where];
            return $this->failWrites ? false : 1;
        }
    }
}

if (!isset ($GLOBALS ['wpdb']))
{
    $GLOBALS ['wpdb'] = new wpdb ();
}

// ── Option / user-meta layer (Settings service) ──

if (!function_exists ('get_option'))
{
    function get_option (string $option, mixed $default = false): mixed
    {
        return $GLOBALS ['radykfw_test_options'] [$option] ?? $default;
    }
}

if (!function_exists ('get_user_meta'))
{
    function get_user_meta (int $userId, string $key = '', bool $single = false): mixed
    {
        return $GLOBALS ['radykfw_test_user_meta'] [$userId] [$key] ?? ($single ? '' : []);
    }
}

if (!function_exists ('get_current_user_id'))
{
    function get_current_user_id (): int
    {
        return (int) ($GLOBALS ['radykfw_test_user_id'] ?? 0);
    }
}

// ── URL / request environment ──

if (!function_exists ('home_url'))
{
    function home_url (string $path = ''): string
    {
        return 'http://example.test' . $path;
    }
}

if (!function_exists ('is_admin'))
{
    function is_admin (): bool
    {
        return (bool) ($GLOBALS ['radykfw_test_is_admin'] ?? false);
    }
}

if (!function_exists ('wp_doing_ajax'))
{
    function wp_doing_ajax (): bool
    {
        return (bool) ($GLOBALS ['radykfw_test_ajax'] ?? false);
    }
}

// ── Content lookups (Router::wpHasRealContent) ──

if (!function_exists ('get_page_by_path'))
{
    function get_page_by_path (string $page_path, string $output = 'OBJECT', string $post_type = 'post'): ?object
    {
        return $GLOBALS ['radykfw_test_pages'] [$page_path] ?? null;
    }
}

if (!function_exists ('get_posts'))
{
    function get_posts (array $args = []): array
    {
        return $GLOBALS ['radykfw_test_posts'] ?? [];
    }
}

// ── Auth gateway / API (used by in-process and subprocess runs) ──

if (!function_exists ('is_user_logged_in'))
{
    function is_user_logged_in (): bool
    {
        return (bool) ($GLOBALS ['radykfw_test_logged_in'] ?? false);
    }
}

if (!function_exists ('wp_get_current_user'))
{
    function wp_get_current_user (): object
    {
        return $GLOBALS ['radykfw_test_current_user']
            ?? (object) ['ID' => 0, 'display_name' => '', 'user_email' => ''];
    }
}

if (!function_exists ('wp_safe_redirect'))
{
    function wp_safe_redirect (string $location, int $status = 302): bool
    {
        $GLOBALS ['radykfw_test_redirects'] [] = $location;
        echo "__REDIRECT__={$location}\n";
        return true;
    }
}

if (!function_exists ('wp_login_url'))
{
    function wp_login_url (string $redirect = ''): string
    {
        return home_url ('/wp-login.php');
    }
}
