<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;
use System\Services\Module;
use System\Services\Cli;

final class ArchitectureExtensionsTest extends TestCase
{
    private string $root;
    private array $registry;

    protected function setUp (): void
    {
        $this->root = __DIR__ . '/../cache/modules-' . bin2hex (random_bytes (5));
        mkdir ($this->root);
        $property = new \ReflectionProperty (Registry::class, 'data');
        $this->registry = $property->getValue ();
        $property->setValue (null, ['modules' => $this->root, 'nsmod' => 'Tests\\Ci\\']);
        Module::reset ();
        $GLOBALS['radykfw_test_logged_in'] = false;
        $GLOBALS['radykfw_test_dispatch'] = [];
    }

    protected function tearDown (): void
    {
        $iterator = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator ($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {$file->isDir () ? rmdir ($file->getPathname ()) : unlink ($file->getPathname ());}
        rmdir ($this->root);
        (new \ReflectionProperty (Registry::class, 'data'))->setValue (null, $this->registry);
        Module::reset ();
        Router::setCurrentModule ('');
        unset ($GLOBALS['radykfw_test_logged_in']);
    }

    private function config (string $module, string $file, array $value): void
    {
        $dir = $this->root . '/' . $module . '/Config';
        if (!is_dir ($dir)) {mkdir ($dir, 0777, true);}
        file_put_contents ($dir . '/' . $file . '.conf.php', '<?php return ' . var_export ($value, true) . ';');
        Module::reset ();
    }

    public function testDiscoveryAndGeneratorAreIndependentOfAutoDiscovery (): void
    {
        $result = Cli::run ('make-module', ['Example'], []);
        self::assertSame ('Example', $result['module']);
        self::assertSame ([], Module::discover ()['errors']);
        self::assertSame ('Tests\\Ci\\Example', Module::discover ()['modules']['Example']['namespace']);
        $this->expectException (\RuntimeException::class);
        Cli::run ('make-module', ['Example'], []);
    }

    public function testGeneratorRejectsTraversal (): void
    {
        $this->expectException (\InvalidArgumentException::class);
        Cli::run ('make-module', ['../System'], []);
    }

    public function testReservedFileIsRejectedBeforeAnyPhpExecution (): void
    {
        $this->config ('Example', 'app', []);
        file_put_contents ($this->root . '/Example/Config/modules.conf.php', '<?php throw new Exception("EXECUTED");');
        $errors = Module::discover ()['errors'];
        self::assertCount (1, $errors);
        self::assertStringContainsString ('system-owned', $errors[0]);
    }

    public function testDependenciesHaveStableTopologicalOrderAndCyclesFail (): void
    {
        $this->config ('Alpha', 'modules', ['namespace' => 'Tests\\Ci\\Alpha', 'depends_on' => ['Zulu'], 'routes' => true]);
        $this->config ('Zulu', 'modules', ['namespace' => 'Tests\\Ci\\Zulu', 'depends_on' => [], 'routes' => true]);
        self::assertSame (['Zulu', 'Alpha'], array_keys (Module::discover ()['modules']));
        $this->config ('Zulu', 'modules', ['namespace' => 'Tests\\Ci\\Zulu', 'depends_on' => ['Alpha'], 'routes' => true]);
        self::assertStringContainsString ('cycle', Module::discover ()['errors'][0]);
    }

    public function testCollisionDiscardsContributions (): void
    {
        $this->config ('Alpha', 'routing', ['aliases' => ['GET /unique' => 'Alpha::index']]);
        $this->config ('Zulu', 'routing', ['aliases' => ['GET /unique' => 'Zulu::index']]);
        self::assertSame ([], Module::discover ()['aliases']);
        self::assertStringContainsString ('Duplicate route', Module::discover ()['errors'][0]);
    }

    public function testAuthDenialClaimsAliasAndDirectDispatch (): void
    {
        $this->config ('Example', 'routing', ['aliases' => ['GET /protected' => ['target' => 'Welcome::index', 'middleware' => ['auth']]]]);
        $saved = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start ();
        try {
            self::assertTrue (Router::tryAliases ());
            self::assertTrue (Router::tryDispatch ('Tests\\Ci\\Welcome\\Controllers\\Welcome', 'index', []));
            self::assertSame ([], $GLOBALS['radykfw_test_dispatch']);
        } finally {ob_end_clean (); $_SERVER = $saved;}
    }

    public function testAuthenticatedRouteDispatches (): void
    {
        $this->config ('Example', 'routing', ['aliases' => ['GET /protected' => ['target' => 'Welcome::index', 'middleware' => ['auth']]]]);
        $GLOBALS['radykfw_test_logged_in'] = true;
        $saved = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        try {
            self::assertTrue (Router::tryAliases ());
            self::assertSame (['Welcome::index'], $GLOBALS['radykfw_test_dispatch']);
        } finally {$_SERVER = $saved;}
    }

    public function testMissingDependencyIsActionable (): void
    {
        $this->config ('Example', 'modules', ['namespace' => 'Tests\\Ci\\Example', 'depends_on' => ['Missing'], 'routes' => true]);
        self::assertStringContainsString ('Unknown dependency', Module::discover ()['errors'][0]);
    }

    public function testSystemRoutingPolicyCannotBeOverridden (): void
    {
        $this->config ('Example', 'routing', ['native' => false, 'aliases' => []]);
        self::assertStringContainsString ('only aliases', Module::discover ()['errors'][0]);
    }

    public function testDisabledModuleCannotDispatch (): void
    {
        $this->config ('Welcome', 'modules', ['namespace' => 'Tests\\Ci\\Welcome', 'depends_on' => [], 'routes' => false]);
        Router::setCurrentModule ('Welcome');
        self::assertTrue (Router::tryDispatch ('Tests\\Ci\\Welcome\\Controllers\\Welcome', 'index', []));
        self::assertSame ([], $GLOBALS['radykfw_test_dispatch']);
        self::assertSame (404, http_response_code ());
    }

    public function testUnknownMiddlewareFailsClosed (): void
    {
        $this->config ('Example', 'routing', ['aliases' => ['GET /protected' => ['target' => 'Welcome::index', 'middleware' => ['missing']]]]);
        ob_start ();
        try {self::assertTrue (Router::tryAliases ()); self::assertSame (500, http_response_code ());}
        finally {ob_end_clean ();}
        self::assertNotEmpty (Cli::run ('test-routes', [], [])['errors']);
    }

    public function testDebugRouteIsReadOnlyAndMatchesPatterns (): void
    {
        $this->config ('Example', 'routing', ['aliases' => ['GET /sample/{id:int}' => 'Welcome::index']]);
        $result = Cli::run ('debug-route', ['/sample/42'], ['format' => 'json']);
        self::assertSame (['42'], $result['matches']['GET /sample/{id:int}']['params']);
        self::assertSame ([], $GLOBALS['radykfw_test_dispatch']);
    }
}
