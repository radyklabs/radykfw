<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;

/**
 * Router::handle() segment dispatch — cases 0/1/2/default plus the
 * security guards (invalid segments) and the Settings-driven toggles
 * (routing.native, modules.auto_discover).
 *
 * Registry `nsmod` is overridden to `Tests\` so controller classes resolve
 * to the fixtures in tests/support/fixtures.php.
 */
final class RouterHandleTest extends TestCase
{
    private bool $registryWasInitialized;
    private array $registryData;

    protected function setUp (): void
    {
        require_once __DIR__ . '/support/fixtures.php';

        $GLOBALS ['radykfw_test_dispatch'] = [];
        $GLOBALS ['radykfw_test_options'] = ['radykfw_config' => ['modules.auto_discover' => true]];

        $registry = new \ReflectionClass (Registry::class);
        $this->registryWasInitialized = $registry->getProperty ('initialized')->getValue ();
        $this->registryData = $registry->getProperty ('data')->getValue ();
        $registry->getProperty ('data')->setValue (null, ['nsmod' => 'Tests\\Ci\\']);

        Router::setCurrentModule ('');
    }

    protected function tearDown (): void
    {
        $registry = new \ReflectionClass (Registry::class);
        $registry->getProperty ('data')->setValue (null, $this->registryData);
        $registry->getProperty ('initialized')->setValue (null, $this->registryWasInitialized);
        Router::setCurrentModule ('');

        $GLOBALS ['radykfw_test_dispatch'] = [];
        $GLOBALS ['radykfw_test_options'] = [];
    }

    public function testRootFallsThroughWhenNativeRoutingEnabled (): void
    {
        $this->assertFalse (Router::handle ([]));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testRootDispatchesDefaultModuleWhenNativeDisabled (): void
    {
        $GLOBALS ['radykfw_test_options'] ['radykfw_config'] = [
            'modules.auto_discover' => true,
            'routing.native'        => false,
        ];

        $this->assertTrue (Router::handle ([]));
        $this->assertSame (['Welcome::index'], $GLOBALS ['radykfw_test_dispatch']);
        $this->assertSame ('Welcome', Router::currentModule ());
    }

    public function testAutoDiscoveryDisabledFallsThroughToWordPress (): void
    {
        $GLOBALS ['radykfw_test_options'] ['radykfw_config'] = [
            'modules.auto_discover' => false,
            'routing.native'        => false,
        ];

        $this->assertFalse (Router::handle (['cifixture']));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testCaseOneSegmentDispatchesModuleControllerIndex (): void
    {
        $this->assertTrue (Router::handle (['cifixture']));
        $this->assertSame (['Cifixture::index'], $GLOBALS ['radykfw_test_dispatch']);
        $this->assertSame ('Cifixture', Router::currentModule ());
    }

    public function testCaseOneMissingModuleReturnsFalse (): void
    {
        $this->assertFalse (Router::handle (['ghostmodule']));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testCaseTwoSegmentsDispatchNamedController (): void
    {
        $this->assertTrue (Router::handle (['cifixture', 'panel']));
        $this->assertSame (['Panel::index'], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testCaseTwoSegmentsMissingControllerDoesNotFallback (): void
    {
        // Per design: named-controller misses (case 2) fall through to WP.
        $this->assertFalse (Router::handle (['cifixture', 'ghost']));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testCaseDefaultPassesExtraSegmentsAsParams (): void
    {
        $this->assertTrue (Router::handle (['cifixture', 'panel', 'save', '42', 'x']));
        $this->assertSame (['Panel::save:42,x'], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testCaseDefaultTooManySegmentsFallbackToIndex (): void
    {
        $this->assertTrue (Router::handle (['cifixture', 'panel', 'save', 'a', 'b', 'c']));
        $this->assertSame (['Panel::index'], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testPathTraversalSegmentsAreRejected (): void
    {
        $this->assertFalse (Router::handle (['../evil']));
        $this->assertFalse (Router::handle (['cifixture', '../evil']));
        $this->assertFalse (Router::handle (['cifixture', 'panel', 'save%2Fpath']));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testNonIdentifierSegmentsAreRejected (): void
    {
        $this->assertFalse (Router::handle (['bad-seg']));
        $this->assertFalse (Router::handle (['cifixture', 'bad seg']));
        $this->assertFalse (Router::handle (['9lives']));
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }
}
