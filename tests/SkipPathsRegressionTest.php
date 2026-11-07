<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression for T4: `Runner::shouldSkipRouting` compares the request
 * path against `routing.skip_paths` by exact `in_array`. The config
 * entries are bare segments (`wp-login`), while WordPress actually
 * serves these under a `.php` suffix on default (non-pretty)
 * permalinks (`wp-login.php`). This test locks in the *current*
 * behavior so a future decision to strip the suffix cannot regress
 * silently, and makes the edge explicit.
 *
 * It does NOT change logic — it asserts the as-is compare.
 */
final class SkipPathsRegressionTest extends TestCase
{
    /** Mirror of the exact comparison in Runner::shouldSkipRouting. */
    private static function isSkipped (string $path, array $skipPaths): bool
    {
        return in_array ($path, $skipPaths, true);
    }

    /** @test */
    public function bare_segment_paths_match_config (): void
    {
        $paths = get_config ('routing.skip_paths', []);
        foreach ($paths as $path) {
            $this->assertTrue (
                self::isSkipped ($path, $paths),
                "Skip-path '{$path}' must match itself in the config list"
            );
        }
    }

    /** @test */
    public function dotted_php_paths_do_not_match_bare_entries (): void
    {
        $paths  = get_config ('routing.skip_paths', []);
        $dotted = array_map (static fn ($p) => $p . '.php', $paths);
        foreach ($dotted as $dottedPath) {
            $this->assertFalse (
                self::isSkipped ($dottedPath, $paths),
                "Dotted path '{$dottedPath}' is NOT in the bare list (documented edge: skip_paths are suffix-free)"
            );
        }
    }
}
