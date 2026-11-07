<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Minimal smoke tests: framework config + pure-utility wiring loads
 * without a WordPress runtime. These guard the test bootstrap and
 * the pure-PHP surface (config accessor + routing shape) against
 * regression.
 */
final class SmokeTest extends TestCase
{
    /** @test */
    public function bootstrap_registers_autoloader (): void
    {
        $this->assertTrue (
            class_exists (\System\Core\Registry::class),
            'PSR-4 autoloader must resolve System\Core\Registry'
        );
    }

    /** @test */
    public function config_accessor_reads_routing_domain (): void
    {
        $native = get_config ('routing.native');
        $this->assertTrue (is_bool ($native), 'routing.native must resolve to a bool');
    }

    /** @test */
    public function skip_paths_returns_string_array (): void
    {
        $paths = get_config ('routing.skip_paths', []);
        $this->assertIsArray ($paths);
        $this->assertNotEmpty ($paths, 'routing.skip_paths must not be empty');
        $this->assertContains ('wp-login', $paths);
    }

    /** @test */
    public function default_module_is_string (): void
    {
        $this->assertSame (
            'Welcome',
            get_config ('routing.default'),
            'routing.default must equal the planned module'
        );
    }
}
