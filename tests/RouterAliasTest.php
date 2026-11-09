<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;

/**
 * Router alias routing: exact match, method gating, miss paths, the
 * private pattern matcher (placeholders / traversal hardening), plus
 * wpHasRealContent, shouldSkipRouting and filterCanonicalRedirect.
 */
final class RouterAliasTest extends TestCase
{
    private bool $registryWasInitialized;
    private array $registryData;
    private array $savedServer;

    protected function setUp (): void
    {
        require_once __DIR__ . '/support/fixtures.php';

        $this->savedServer = $_SERVER;

        $GLOBALS ['radykfw_test_dispatch'] = [];
        $GLOBALS ['radykfw_test_options'] = ['radykfw_config' => ['modules.auto_discover' => true]];
        $GLOBALS ['radykfw_test_pages'] = [];
        $GLOBALS ['radykfw_test_posts'] = [];
        $GLOBALS ['radykfw_test_is_admin'] = false;
        $GLOBALS ['radykfw_test_ajax'] = false;

        $registry = new \ReflectionClass (Registry::class);
        $this->registryWasInitialized = $registry->getProperty ('initialized')->getValue ();
        $this->registryData = $registry->getProperty ('data')->getValue ();
        $registry->getProperty ('data')->setValue (null, ['nsmod' => 'Tests\\Ci\\']);

        Router::setCurrentModule ('');
    }

    protected function tearDown (): void
    {
        $_SERVER = $this->savedServer;

        $registry = new \ReflectionClass (Registry::class);
        $registry->getProperty ('data')->setValue (null, $this->registryData);
        $registry->getProperty ('initialized')->setValue (null, $this->registryWasInitialized);
        Router::setCurrentModule ('');

        $GLOBALS ['radykfw_test_dispatch'] = [];
        $GLOBALS ['radykfw_test_options'] = [];
        $GLOBALS ['radykfw_test_pages'] = [];
        $GLOBALS ['radykfw_test_posts'] = [];
        $GLOBALS ['radykfw_test_is_admin'] = false;
        $GLOBALS ['radykfw_test_ajax'] = false;
    }

    private function request (string $uri, string $method = 'GET'): void
    {
        $_SERVER ['REQUEST_URI'] = $uri;
        $_SERVER ['REQUEST_METHOD'] = $method;
    }

    // ── tryAliases ──

    public function testExactAliasMatchDispatchesFixture (): void
    {
        $this->request ('/welcome');

        $this->assertTrue (Router::tryAliases ());
        $this->assertSame (['Welcome::index'], $GLOBALS ['radykfw_test_dispatch']);
        $this->assertSame ('Welcome', Router::currentModule ());
    }

    public function testUnknownPathMissesAliases (): void
    {
        $this->request ('/definitely-not-an-alias');

        $this->assertFalse (Router::tryAliases ());
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testHttpMethodGatesAliasLookup (): void
    {
        $this->request ('/welcome', 'POST');

        $this->assertFalse (Router::tryAliases ());
        $this->assertSame ([], $GLOBALS ['radykfw_test_dispatch']);
    }

    public function testMissingRequestUriMissesAliases (): void
    {
        unset ($_SERVER ['REQUEST_URI']);
        $_SERVER ['REQUEST_METHOD'] = 'GET';

        $this->assertFalse (Router::tryAliases ());
    }

    // ── matchAliasPattern (private, via reflection) ──

    private function match (string $pattern, string $path): array
    {
        // matchAliasPattern is private static and takes &$params by reference.
        // Closure::bind with scope Router::class resolves the private method
        // in the class context, so the reference propagates to the local variable.
        $closure = \Closure::bind (
            static function (string $pattern, string $path): array {
                $params = [];
                $matched = self::matchAliasPattern ($pattern, $path, $params);
                return [$matched, $params];
            },
            null,
            \System\Core\Router::class
        );
        return $closure ($pattern, $path);
    }

    public function testPatternMatchesStringAndIntPlaceholders (): void
    {
        [$matched, $params] = $this->match ('door/{slug}', 'door/42-detail');
        $this->assertTrue ($matched);
        $this->assertSame (['42-detail'], $params);

        [$matched, $params] = $this->match ('home/{id:int}', 'home/42');
        $this->assertTrue ($matched);
        $this->assertSame (['42'], $params);

        [$matched, $params] = $this->match ('a/{slug}/c', 'a/b-c_1/c');
        $this->assertTrue ($matched);
        $this->assertSame (['b-c_1'], $params);
    }

    public function testPatternRejectsTypeMismatch (): void
    {
        [$matched] = $this->match ('home/{id:int}', 'home/abc');
        $this->assertFalse ($matched);

        [$matched] = $this->match ('a/{slug}/c', 'a/has.dot/c');
        $this->assertFalse ($matched);
    }

    public function testPatternRequiresEqualSegmentCounts (): void
    {
        [$matched] = $this->match ('a/{x}', 'a/b/c');
        $this->assertFalse ($matched);

        [$matched] = $this->match ('a/{x}', 'a');
        $this->assertFalse ($matched);
    }

    public function testPatternBlocksTraversalAndInjectionSegments (): void
    {
        foreach (['..', '../etc', 'a/b', "a'b", 'a"b', 'a b', 'a%2Fb'] as $evil)
        {
            [$matched] = $this->match ('x/{seg}', 'x/' . $evil);
            $this->assertFalse ($matched, "path segment {$evil} must not match {seg}");
        }
    }

    public function testPatternLiteralMustMatchExactly (): void
    {
        [$matched] = $this->match ('literal/path', 'literal/other');
        $this->assertFalse ($matched);

        [$matched] = $this->match ('literal/path', 'literal/path');
        $this->assertTrue ($matched);
    }

    // ── wpHasRealContent ──

    public function testWpHasRealContentRejectsEmptyAndDottedPaths (): void
    {
        $this->assertFalse (Router::wpHasRealContent (''));
        $this->assertFalse (Router::wpHasRealContent ('/'));
        $this->assertFalse (Router::wpHasRealContent ('style.css'));
        $this->assertFalse (Router::wpHasRealContent ('dir/file.php'));
    }

    public function testWpHasRealContentFindsPage (): void
    {
        $GLOBALS ['radykfw_test_pages'] ['about'] = (object) ['ID' => 5];

        $this->assertTrue (Router::wpHasRealContent ('about'));
    }

    public function testWpHasRealContentFindsPublishedPost (): void
    {
        $GLOBALS ['radykfw_test_posts'] = [(object) ['ID' => 9]];

        $this->assertTrue (Router::wpHasRealContent ('hello-world'));
    }

    public function testWpHasRealContentReturnsFalseWhenNothingExists (): void
    {
        $this->assertFalse (Router::wpHasRealContent ('no-such-page'));
    }

    // ── shouldSkipRouting ──

    private function wp (): \WP
    {
        $wp = new \WP ();
        $wp->query_vars = [];
        return $wp;
    }

    public function testShouldSkipRoutingSkipsAdminAndAjax (): void
    {
        $GLOBALS ['radykfw_test_is_admin'] = true;
        $this->assertTrue (Router::shouldSkipRouting ('anything', $this->wp ()));

        $GLOBALS ['radykfw_test_is_admin'] = false;
        $GLOBALS ['radykfw_test_ajax'] = true;
        $this->assertTrue (Router::shouldSkipRouting ('anything', $this->wp ()));
    }

    public function testShouldSkipRoutingSkipsRestAndConfiguredPaths (): void
    {
        $this->assertTrue (Router::shouldSkipRouting ('wp-json/wp/v2/posts', $this->wp ()));
        $this->assertTrue (Router::shouldSkipRouting ('rest/v2/items', $this->wp ()));
        $this->assertTrue (Router::shouldSkipRouting ('wp-login', $this->wp ()));
        $this->assertTrue (Router::shouldSkipRouting ('xmlrpc', $this->wp ()));
    }

    public function testShouldSkipRoutingSkipsStaticExtensions (): void
    {
        $this->assertTrue (Router::shouldSkipRouting ('assets/app.css', $this->wp ()));
        $this->assertTrue (Router::shouldSkipRouting ('logo.PNG', $this->wp ()));
    }

    public function testShouldSkipRoutingSkipsAlreadyHandledRequests (): void
    {
        $wp = $this->wp ();
        $wp->query_vars ['radykfw_handled'] = true;

        $this->assertTrue (Router::shouldSkipRouting ('welcome', $wp));
    }

    public function testShouldSkipRoutingClaimsNormalPages (): void
    {
        $this->assertFalse (Router::shouldSkipRouting ('welcome', $this->wp ()));
        $this->assertFalse (Router::shouldSkipRouting ('', $this->wp ()));
    }

    public function testGetRequestPathNormalisesSlashes (): void
    {
        $wp = new \WP ();
        $wp->request = '/hello-world/';

        $this->assertSame ('hello-world', Router::getRequestPath ($wp));
    }

    // ── filterCanonicalRedirect ──

    public function testFilterCanonicalRedirectBlocksClaimedAliasesOnly (): void
    {
        $redirect = 'http://example.test/changelog/';

        // Configured literal alias: redirect suppressed.
        $this->assertFalse (Router::filterCanonicalRedirect ($redirect, 'http://example.test/changelog'));

        // Unrelated URL: redirect allowed unchanged.
        $this->assertSame (
            $redirect,
            Router::filterCanonicalRedirect ($redirect, 'http://example.test/other-page')
        );
    }
}
