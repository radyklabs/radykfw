<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;
use System\Services\Drip;

final class DripResolverTest extends TestCase
{
    private string $modulesRoot;
    private string $systemRoot;
    private string $cacheRoot;
    private array $registry;

    protected function setUp (): void
    {
        // Fresh fixture roots so the tests never touch the real plugin files.
        $this->modulesRoot = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-modules-' . bin2hex (random_bytes (5));
        $this->systemRoot  = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-system-' . bin2hex (random_bytes (5));
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
    }

    protected function tearDown (): void
    {
        foreach ([$this->modulesRoot, $this->systemRoot, $this->cacheRoot] as $root)
        {
            if (! is_dir ($root)) {continue;}
            $iterator = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator ($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file)
            {
                $file->isDir () ? rmdir ($file->getPathname ()) : unlink ($file->getPathname ());
            }
            rmdir ($root);
        }
        Router::setCurrentModule ('');
        $this->restoreRegistry ();
    }

    private function restoreRegistry (): void
    {
        $property = new \ReflectionProperty (Registry::class, 'data');
        $property->setValue (null, $this->registry);
    }

    private function writeModuleView (string $module, string $view, string $output = 'OUT_MODULE'): string
    {
        $dir = $this->modulesRoot . '/' . $module . '/Views';
        if (str_contains ($view, '/'))
        {
            mkdir ($dir . '/' . dirname ($view), 0777, true);
        }
        else
        {
            mkdir ($dir, 0777, true);
        }
        $file = $dir . '/' . $view . '.view.php';
        file_put_contents ($file, '<?php' . "\n" . "echo '" . $output . "';");
        $this->assertTrue (is_file ($file), "fixture not created: {$file}");
        return $file;
    }

    private function writeSystemTemplate (string $file, string $output = 'OUT_SYSTEM'): string
    {
        $path = $this->systemRoot . '/Templates/' . $file;
        mkdir (dirname ($path), 0777, true);
        // `.error.html` sources stay plain; `.page.php` sources echo the marker.
        $content = str_ends_with ($file, '.error.html') ? $output : '<?php' . "\n" . "echo '" . $output . "';";
        file_put_contents ($path, $content);
        $this->assertTrue (is_file ($path), "fixture not created: {$path}");
        return $path;
    }

    /** @test */
    public function dripSuffixInHelpersSelectsRendererWithoutChangingPhysicalFile (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Welcome', 'partials/card', 'HELPER_MODULE');
        $this->writeSystemTemplate ('pages/dashboard.page.php', 'HELPER_SYSTEM');

        $this->assertSame ('HELPER_MODULE', \view ('partials.card.drip', [], true));
        $this->assertSame ('HELPER_SYSTEM', \system_view ('pages.dashboard.drip', [], true));

        // Without the .drip suffix the legacy include path must render the
        // same physical files unchanged (regression safety).
        $this->assertSame ('HELPER_MODULE', \view ('partials.card', [], true));
        $this->assertSame ('HELPER_SYSTEM', \system_view ('pages/dashboard', [], true));
    }

    /** @test */
    public function errorView404HelperResolvesPlainRender (): void
    {
        $this->writeSystemTemplate ('errors/404.error.html', 'HELPER_404');

        $this->assertSame ('HELPER_404', \error_view ('404', [], true));
        $this->assertSame ('HELPER_404', \error_view ('404.drip', [], true));
    }

    /** @test */
    public function moduleResolverFindsViewOfCurrentModule (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Welcome', 'home');

        $this->assertSame (
            $this->modulesRoot . '/Welcome/Views/home.view.php',
            Drip::resolveModule ('module:home')
        );
    }

    /** @test */
    public function dotNotationMapsToSubfolders (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Welcome', 'partials/card');

        $this->assertSame (
            $this->modulesRoot . '/Welcome/Views/partials/card.view.php',
            Drip::resolveModule ('module:partials.card')
        );
    }

    /** @test */
    public function capitalizedFirstSegmentSelectsExplicitModule (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Login', 'home');

        $this->assertSame (
            $this->modulesRoot . '/Login/Views/home.view.php',
            Drip::resolveModule ('module:Login.home')
        );
    }

    /** @test */
    public function dripSuffixSelectsSamePhysicalFile (): void
    {
        Router::setCurrentModule ('Welcome');
        $this->writeModuleView ('Welcome', 'home', 'OUT_DRIP');

        $resolved = Drip::resolveModule ('module:home.drip');

        $this->assertSame ($this->modulesRoot . '/Welcome/Views/home.view.php', $resolved);
        $this->assertSame ('OUT_DRIP', Drip::render ('module:home.drip'));
    }

    /** @test */
    public function systemDotNotationResolvesSameFileAsLegacyResolver (): void
    {
        // Legacy system_view('pages.dashboard') resolves Templates/pages/dashboard.page.php;
        // the Drip identifier must hit the same physical file.
        $this->writeSystemTemplate ('pages/dashboard.page.php');

        $this->assertSame (
            $this->systemRoot . '/Templates/pages/dashboard.page.php',
            Drip::resolveSystem ('system:pages.dashboard.drip')
        );
    }

    /** @test */
    public function absolutePathsAreRejected (): void
    {
        foreach (['module:/etc/passwd.drip', 'system:C:\\Windows\\win.ini.drip', 'error:/etc/passwd.drip'] as $identifier)
        {
            try
            {
                Drip::render ($identifier);
                $this->fail ("Expected rejection of identifier '{$identifier}'");
            }
            catch (\InvalidArgumentException $exception)
            {
                $this->assertStringContainsString ('absolute path', $exception->getMessage ());
            }
        }
    }

    /** @test */
    public function traversalAndNullBytesAreRejected (): void
    {
        foreach (['module:../secret.drip', "system:a\0b.drip"] as $identifier)
        {
            try
            {
                Drip::render ($identifier);
                $this->fail ("Expected rejection of identifier " . json_encode ($identifier));
            }
            catch (\InvalidArgumentException $exception)
            {
                // rejected — as required
                $this->assertNotEmpty ($exception->getMessage ());
            }
        }
    }

    /** @test */
    public function missingTemplateFailsExplicitlyWithoutAbsolutePathLeak (): void
    {
        Router::setCurrentModule ('Welcome');

        try
        {
            Drip::render ('module:missing');
            $this->fail ('Expected RuntimeException for missing module view');
        }
        catch (\RuntimeException $exception)
        {
            $this->assertStringContainsString ("'missing'", $exception->getMessage ());
            $this->assertStringNotContainsString ($this->modulesRoot, $exception->getMessage ());
        }
    }

    /** @test */
    public function errorView404ResolvesPlainHtmlSource (): void
    {
        $this->writeSystemTemplate ('errors/404.error.html');

        $this->assertSame (
            $this->systemRoot . '/Templates/errors/404.error.html',
            Drip::resolveError ('error:404')
        );
    }

    /** @test */
    public function errorView404DripUsesSameSourceFileThroughRenderer (): void
    {
        $this->writeSystemTemplate ('errors/404.error.html', '<?php echo $code ?? "404";');

        $this->assertSame (
            $this->systemRoot . '/Templates/errors/404.error.html',
            Drip::resolveError ('error:404.drip')
        );
        $this->assertSame (
            '404',
            Drip::render ('error:404.drip')
        );
        $this->assertSame (
            'CUSTOM',
            Drip::render ('error:404.drip', ['code' => 'CUSTOM'])
        );
    }
}
