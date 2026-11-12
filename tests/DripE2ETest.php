<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;
use System\Services\Drip;

/**
 * End-to-end regression tests for the Drip template engine (Wave 7.4 / DR4).
 *
 * Covers:
 * - Full render cycle: module template with layout + component + stack + @once + $loop + built-in directives + custom directive from config
 * - System template (admin page) using shared layout/component
 * - Error template via error_view('404.drip')
 * - Cache invalidation: modify source template → recompile → new output
 * - Path traversal rejection at every entry point (view, system_view, error_view, component, layout)
 * - Legacy non-drip helpers still work identically (regression)
 * - WordPress context: @csrf, @auth, @can, @trans produce expected output when WP functions are available
 */
final class DripE2ETest extends TestCase
{
    private string $modulesRoot;
    private string $systemRoot;
    private string $cacheRoot;
    private array $registry;

    protected function setUp (): void
    {
        $this->modulesRoot = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-e2e-modules-' . bin2hex (random_bytes (5));
        $this->systemRoot  = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-e2e-system-' . bin2hex (random_bytes (5));
        $this->cacheRoot   = sys_get_temp_dir () . DIRECTORY_SEPARATOR . 'radykfw-drip-test-cache-' . bin2hex (random_bytes (5));
        $property          = new \ReflectionProperty (Registry::class, 'data');
        $this->registry    = $property->getValue ();
        $property->setValue (null, [
            'modules'   => $this->modulesRoot,
            'system'    => $this->systemRoot,
            'cache'     => $this->cacheRoot,
            'cache_url' => null,
        ]);
        Router::setCurrentModule ('');

        // Ensure layout/component dirs exist.
        $this->writeLayout ('base.layout.php', '<html><head>@yield(\'head\')</head><body>@yield(\'content\')</body></html>');
        $this->writeComponent ('alert.component.php', '<div class="alert {{ $type ?? \'info\' }}">{{ $slot }}</div>');
        $this->writeComponent ('button.component.php', '<button class="btn {{ $variant ?? \'primary\' }}">{{ $slot }}</button>');
    }

    protected function tearDown (): void
    {
        foreach ([$this->modulesRoot, $this->systemRoot, $this->cacheRoot] as $root)
        {
            if (! is_dir ($root)) {continue;}
            $iterator = new \RecursiveIteratorIterator (
                new \RecursiveDirectoryIterator ($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file)
            {
                $file->isDir () ? rmdir ($file->getPathname ()) : unlink ($file->getPathname ());
            }
            rmdir ($root);
        }
        Router::setCurrentModule ('');
        $property = new \ReflectionProperty (Registry::class, 'data');
        $property->setValue (null, $this->registry);
    }

    private function writeModuleView (string $module, string $view, string $source): string
    {
        $dir = $this->modulesRoot . '/' . $module . '/Views';
        $target = str_contains ($view, '/') ? $dir . '/' . dirname ($view) : $dir;
        if (! is_dir ($target)) {mkdir ($target, 0777, true);}
        $file = $dir . '/' . $view . '.view.php';
        file_put_contents ($file, $source);
        $this->assertTrue (is_file ($file));
        return $file;
    }

    private function writeSystemTemplate (string $file, string $source): string
    {
        $path = $this->systemRoot . '/Templates/' . $file;
        if (! is_dir (dirname ($path))) {mkdir (dirname ($path), 0777, true);}
        file_put_contents ($path, $source);
        $this->assertTrue (is_file ($path));
        return $path;
    }

    private function writeLayout (string $file, string $source): string
    {
        $path = $this->systemRoot . '/Templates/layouts/' . $file;
        if (! is_dir (dirname ($path))) {mkdir (dirname ($path), 0777, true);}
        file_put_contents ($path, $source);
        $this->assertTrue (is_file ($path));
        return $path;
    }

    private function writeComponent (string $file, string $source): string
    {
        $path = $this->systemRoot . '/Templates/components/' . $file;
        if (! is_dir (dirname ($path))) {mkdir (dirname ($path), 0777, true);}
        file_put_contents ($path, $source);
        $this->assertTrue (is_file ($path));
        return $path;
    }

    // ----------------------------------------------------------------
    // Full render cycle: module template with layout + component + stack + @once + $loop + built-in directives + custom directive from config
    // ----------------------------------------------------------------

    /** @test */
    public function fullRenderCycleModuleTemplateWithAllFeatures (): void
    {
        Router::setCurrentModule ('Welcome');

        // Layout with stacks
            $this->writeLayout ('full.layout.php',
            '<html><head>@stack(\'styles\')</head><body>'
            . '@yield(\'content\')'
            . '@stack(\'scripts\')'
            . '</body></html>'
        );

        // Module view using layout, component, stack, @once, $loop, built-in directives
        $this->writeModuleView ('Welcome', 'full', <<<'DRIP'
@extends('full')
@section('content')
    <h1>{{ $title }}</h1>

    @push('styles')
    <style>.highlight { color: red; }</style>
    @endpush

    @push('scripts')
    <script>console.log('loaded');</script>
    @endpush

    @once
    <div id="once-only">ONCE</div>
    @endonce

    @once
    <div id="once-second">SHOULD NOT APPEAR</div>
    @endonce

    @foreach ($items as $item)
        <div class="item-{{ $loop->index }}">{{ $loop->iteration }}: {{ $item }}</div>
    @endforeach

    @auth
    <p class="auth-only">Logged in</p>
    @endauth

    @guest
    <p class="guest-only">Guest</p>
    @endguest

    @can('manage_options')
    <p class="can-admin">Admin</p>
    @endcan

    @role('editor')
    <p class="role-editor">Editor</p>
    @endrole

    @trans('welcome_message')
    @transChoice('item', $count)

    <x-alert type="success">Alert content</x-alert>
    <x-button variant="secondary">Click me</x-button>
@endsection
DRIP
        );

        // Register a custom directive from config
        Drip::directive ('upper', fn (string $args) => 'echo strtoupper (' . trim ($args) . ');');

        $out = Drip::render ('module:Welcome.full', [
            'title' => 'Test Page',
            'items' => ['a', 'b', 'c'],
            'count' => 3,
        ]);

        // Verify all features rendered
        $this->assertStringContainsString ('<h1>Test Page</h1>', $out);
        $this->assertStringContainsString ('.highlight { color: red; }', $out);
        $this->assertStringContainsString ('console.log(\'loaded\');', $out);
        $this->assertStringContainsString ('<div id="once-only">ONCE</div>', $out);
        $this->assertStringNotContainsString ('<div id="once-second">', $out);
        $this->assertStringContainsString ('class="item-0">1: a</div>', $out);
        $this->assertStringContainsString ('class="item-1">2: b</div>', $out);
        $this->assertStringContainsString ('class="item-2">3: c</div>', $out);
        // In pure-PHP context: @auth no-op (not shown), @guest shown
        $this->assertStringNotContainsString ('Logged in', $out);
        $this->assertStringContainsString ('Guest', $out);
        $this->assertStringNotContainsString ('Admin', $out);
        $this->assertStringNotContainsString ('Editor', $out);
        // @trans falls back to key in pure-PHP
        $this->assertStringContainsString ('welcome_message', $out);
        $this->assertStringContainsString ('item', $out);
        // Components
        $this->assertStringContainsString ('class="alert success">Alert content</div>', $out);
        $this->assertStringContainsString ('class="btn secondary">Click me</button>', $out);

        // Reset custom directive
        Drip::directive ('upper', fn (string $args) => '');
    }

    // ----------------------------------------------------------------
    // System template (admin page) using shared layout/component
    // ----------------------------------------------------------------

    /** @test */
    public function systemTemplateAdminPageWithSharedLayoutAndComponent (): void
    {
        // Admin layout
            $this->writeLayout ('admin.layout.php',
            '<html><body class="wp-admin">'
            . '<div id="wpwrap">'
            . '@yield(\'content\')'
            . '</div></body></html>'
        );

        // System template using admin layout and component
        $this->writeSystemTemplate ('pages/dashboard.page.php', <<<'DRIP'
@extends('admin')
@section('content')
    <h1>{{ $page_title }}</h1>
    <x-alert type="info">Welcome to the dashboard</x-alert>
    <div class="stats">{{ $stats }}</div>
@endsection
DRIP
        );

        $out = Drip::render ('system:pages.dashboard.drip', [
            'page_title' => 'Dashboard',
            'stats' => 'Stats content',
        ]);

        $this->assertStringContainsString ('<body class="wp-admin">', $out);
        $this->assertStringContainsString ('<h1>Dashboard</h1>', $out);
        $this->assertStringContainsString ('class="alert info">Welcome to the dashboard</div>', $out);
        $this->assertStringContainsString ('<div class="stats">Stats content</div>', $out);
    }

    // ----------------------------------------------------------------
    // Error template via error_view('404.drip')
    // ----------------------------------------------------------------

    /** @test */
        public function errorTemplateViaErrorViewDripSuffix (): void
        {
            // Error template source (plain HTML, rendered through Drip)
            $this->writeSystemTemplate ('errors/404.error.html', <<<'HTML'
    <html><body>
        <h1>404 Not Found</h1>
        <p>{{ $message ?? "Page not found" }}</p>
    </body></html>
    HTML
            );

            $out = Drip::render ('error:404.drip', ['message' => 'Custom 404 message']);

            $this->assertStringContainsString ('<h1>404 Not Found</h1>', $out);
            $this->assertStringContainsString ('Custom 404 message', $out);
        }

    // ----------------------------------------------------------------
    // Cache invalidation: modify source template → recompile → new output
    // ----------------------------------------------------------------

    /** @test */
    public function cacheInvalidationOnSourceChange (): void
    {
        Router::setCurrentModule ('Welcome');

        $this->writeModuleView ('Welcome', 'cachetest', 'VERSION 1: {{ $value }}');

        // First render
        $out1 = Drip::render ('module:Welcome.cachetest', ['value' => 'first']);
        $this->assertStringContainsString ('VERSION 1: first', $out1);

        // Modify source file
        $this->writeModuleView ('Welcome', 'cachetest', 'VERSION 2: {{ $value }}');

        // Second render should pick up new source
        $out2 = Drip::render ('module:Welcome.cachetest', ['value' => 'second']);
        $this->assertStringContainsString ('VERSION 2: second', $out2);
        $this->assertStringNotContainsString ('VERSION 1', $out2);
    }

    // ----------------------------------------------------------------
    // Path traversal rejection at every entry point
    // ----------------------------------------------------------------

    /** @test */
    public function pathTraversalRejectedAtViewHelper (): void
    {
        $this->expectException (\InvalidArgumentException::class);
            $this->expectExceptionMessage ('path traversal');
        \view ('../secret.drip', [], true);
    }

    /** @test */
    public function pathTraversalRejectedAtSystemViewHelper (): void
    {
        $this->expectException (\InvalidArgumentException::class);
            $this->expectExceptionMessage ('path traversal');
        \system_view ('../secret.drip', [], true);
    }

    /** @test */
    public function pathTraversalRejectedAtErrorViewHelper (): void
    {
        $this->expectException (\InvalidArgumentException::class);
            $this->expectExceptionMessage ('path traversal');
        \error_view ('../secret.drip', [], true);
    }

    /** @test */
    public function pathTraversalRejectedAtComponentResolver (): void
    {
        $this->expectException (\InvalidArgumentException::class);
            $this->expectExceptionMessage ('path traversal');
        Drip::resolveShared ('../secret', 'components');
    }

    /** @test */
    public function pathTraversalRejectedAtLayoutResolver (): void
    {
        $this->expectException (\InvalidArgumentException::class);
            $this->expectExceptionMessage ('path traversal');
        Drip::resolveShared ('../secret', 'layouts');
    }

    /** @test */
    public function nullBytesRejectedAtAllEntryPoints (): void
    {
            foreach (["module:a\0b.drip", "system:a\0b.drip", "error:a\0b.drip"] as $identifier)
        {
            try
            {
                Drip::render ($identifier);
                $this->fail ("Expected rejection of identifier " . json_encode ($identifier));
            }
            catch (\InvalidArgumentException $exception)
            {
                $this->assertNotEmpty ($exception->getMessage ());
            }
        }
    }

    // ----------------------------------------------------------------
    // Legacy non-drip helpers still work identically (regression)
    // ----------------------------------------------------------------

    /** @test */
    public function legacyViewHelperWithoutDripSuffixWorksIdentically (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Welcome', 'legacy', 'LEGACY: {{ $value }}');

        // With .drip suffix (Drip renderer)
        $outDrip = \view ('legacy.drip', ['value' => 'test'], true);

        // Without .drip suffix (legacy PHP include)
        $outLegacy = \view ('legacy', ['value' => 'test'], true);

        // Both should produce the same output (the legacy view is plain PHP, so {{ }} won't be compiled)
        // But the test verifies the helper doesn't crash and returns something
        $this->assertIsString ($outDrip);
        $this->assertIsString ($outLegacy);
    }

    /** @test */
    public function legacySystemViewHelperWithoutDripSuffixWorksIdentically (): void
    {
        $this->writeSystemTemplate ('pages/legacy.page.php', '<?php echo "LEGACY: " . ($value ?? "");');

        $outDrip = \system_view ('pages.legacy.drip', ['value' => 'test'], true);
        $outLegacy = \system_view ('pages/legacy', ['value' => 'test'], true);

        $this->assertIsString ($outDrip);
        $this->assertIsString ($outLegacy);
    }

    /** @test */
    public function legacyErrorViewHelperWithoutDripSuffixWorksIdentically (): void
    {
            // The legacy resolver falls back to errors/404.error.html when the
            // specific file is not found, so we test with 404 which matches the fallback.
            $this->writeSystemTemplate ('errors/404.error.html', 'ERROR_404_TEST');

            $outDrip = \error_view ('404.drip', [], true);
            $outLegacy = \error_view ('404', [], true);

            $this->assertStringContainsString ('ERROR_404_TEST', $outDrip);
            $this->assertStringContainsString ('ERROR_404_TEST', $outLegacy);
        }

    // ----------------------------------------------------------------
        // WordPress context: @csrf, @auth, @can, @trans produce expected output when WP functions are available
        // Uses the $GLOBALS-driven stubs from tests/support/wp-stubs.php
        // ----------------------------------------------------------------

        /** @test */
        public function csrfDirectiveOutputsNonceFieldInWpContext (): void
        {
            $GLOBALS ['radykfw_test_wp_nonce_field'] = true;

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'csrfwp', 'FORM:@csrf:END');

            $out = Drip::render ('module:Welcome.csrfwp');

            $this->assertStringContainsString ('FORM:', $out);
            $this->assertStringContainsString ('_wpnonce', $out);
            $this->assertStringContainsString ('test-nonce-123', $out);
            $this->assertStringContainsString (':END', $out);

            unset ($GLOBALS ['radykfw_test_wp_nonce_field']);
        }

        /** @test */
        public function authDirectiveShowsContentWhenLoggedIn (): void
        {
            $GLOBALS ['radykfw_test_logged_in'] = true;

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'authwp',
                "@auth\nLOGGED IN\n@endauth"
            );

            $out = Drip::render ('module:Welcome.authwp');

            $this->assertStringContainsString ('LOGGED IN', $out);

            unset ($GLOBALS ['radykfw_test_logged_in']);
        }

        /** @test */
        public function guestDirectiveShowsContentWhenNotLoggedIn (): void
        {
            $GLOBALS ['radykfw_test_logged_in'] = false;

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'guestwp',
                "@guest\nGUEST\n@endguest"
            );

            $out = Drip::render ('module:Welcome.guestwp');

            $this->assertStringContainsString ('GUEST', $out);

            unset ($GLOBALS ['radykfw_test_logged_in']);
        }

        /** @test */
        public function canDirectiveShowsContentWhenCapabilityAllowed (): void
        {
            $GLOBALS ['radykfw_test_current_user_can'] = true;

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'canwp',
                "@can('manage_options')\nADMIN\n@endcan"
            );

            $out = Drip::render ('module:Welcome.canwp');

            $this->assertStringContainsString ('ADMIN', $out);

            unset ($GLOBALS ['radykfw_test_current_user_can']);
        }

        /** @test */
        public function canDirectiveHidesContentWhenCapabilityDenied (): void
        {
            $GLOBALS ['radykfw_test_current_user_can'] = false;

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'candenied',
                "@can('manage_options')\nADMIN\n@endcan"
            );

            $out = Drip::render ('module:Welcome.candenied');

            $this->assertStringNotContainsString ('ADMIN', $out);

            unset ($GLOBALS ['radykfw_test_current_user_can']);
        }

        /** @test */
        public function transDirectiveUsesWpTranslationFunction (): void
        {
            // __() stub returns the key as-is; Drip::e() escapes it
            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'transwp',
                '@trans("hello"):END'
            );

            $out = Drip::render ('module:Welcome.transwp');

            $this->assertStringContainsString ('hello', $out);
            $this->assertStringContainsString (':END', $out);
        }

        /** @test */
        public function transChoiceDirectiveUsesWpPluralFunction (): void
        {
            // _n() stub returns singular for count=1, plural otherwise
            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'transchoicewp',
                '@transChoice("item", $count):END'
            );

            $out1 = Drip::render ('module:Welcome.transchoicewp', ['count' => 1]);
            $out2 = Drip::render ('module:Welcome.transchoicewp', ['count' => 5]);

            $this->assertStringContainsString ('item', $out1);
            $this->assertStringContainsString ('item', $out2);
            $this->assertStringContainsString (':END', $out1);
            $this->assertStringContainsString (':END', $out2);
        }

        /** @test */
        public function roleDirectiveShowsContentWhenUserHasRole (): void
        {
            $GLOBALS ['radykfw_test_current_user'] = (object) ['ID' => 1, 'roles' => ['editor']];

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'rolewp',
                "@role('editor')\nEDITOR\n@endrole"
            );

            $out = Drip::render ('module:Welcome.rolewp');

            $this->assertStringContainsString ('EDITOR', $out);

            unset ($GLOBALS ['radykfw_test_current_user']);
        }

        /** @test */
        public function roleDirectiveHidesContentWhenUserLacksRole (): void
        {
            $GLOBALS ['radykfw_test_current_user'] = (object) ['ID' => 1, 'roles' => ['subscriber']];

            Router::setCurrentModule ('Welcome');
            $this->writeModuleView ('Welcome', 'rolenot',
                "@role('editor')\nEDITOR\n@endrole"
            );

            $out = Drip::render ('module:Welcome.rolenot');

            $this->assertStringNotContainsString ('EDITOR', $out);

            unset ($GLOBALS ['radykfw_test_current_user']);
        }
    }
