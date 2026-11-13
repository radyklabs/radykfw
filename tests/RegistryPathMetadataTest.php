<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use System\Core\Registry;

if (!defined ('ABSPATH')) {define ('ABSPATH', __DIR__ . '/');}
if (!defined ('sep')) {define ('sep', DIRECTORY_SEPARATOR);}
if (!defined ('modules_dir')) {define ('modules_dir', 'modules');}
if (!defined ('system_dir')) {define ('system_dir', 'system');}

require_once __DIR__ . '/../.supports';
require_once __DIR__ . '/../system/Core/Registry.core.php';

final class RegistryPathMetadataTest extends TestCase
{
    /** @test */
    public function paths_and_namespaces_are_generated_from_metadata (): void
    {
        $pluginPath = interlace (sys_get_temp_dir (), 'radykfw-registry-test');
        $pluginUrl = 'https://example.test/plugin/';
        $pathMeta = require __DIR__ . '/../system/Config/path_meta.conf.php';
        $registerPaths = new ReflectionMethod (Registry::class, 'registerPaths');
        $registry = $registerPaths->invoke (null, [
            'plugin_path' => $pluginPath,
            'plugin_url' => $pluginUrl,
        ], $pathMeta);

        $this->assertSame (interlace ($pluginPath, 'modules'), $registry ['modules']);
        $this->assertSame ('Modules\\', $registry ['nsmod']);
        $this->assertSame (interlace ($pluginPath, 'system'), $registry ['system']);
        $this->assertSame ('System\\', $registry ['nssys']);
        $this->assertSame (interlace ($pluginPath, 'assets'), $registry ['assets']);
        $this->assertSame ('https://example.test/plugin/assets', $registry ['assets_url']);

        $this->assertSame (interlace ($registry ['system'], 'Config'), $registry ['config']);
        $this->assertSame ('.config.php', $registry ['xtconfig']);
        $this->assertSame (interlace ($registry ['system'], 'Contracts'), $registry ['contracts']);
        $this->assertSame ('.contract.php', $registry ['xtcontracts']);
        $this->assertSame ('System\\Contracts\\', $registry ['nscontracts']);
        $this->assertSame (interlace ($registry ['system'], 'Core'), $registry ['core']);
        $this->assertSame ('.core.php', $registry ['xtcore']);
        $this->assertSame ('System\\Core\\', $registry ['nscore']);
        $this->assertSame (interlace ($registry ['system'], 'Enum'), $registry ['enum']);
        $this->assertSame ('.enum.php', $registry ['xtenum']);
        $this->assertSame ('System\\Enum\\', $registry ['nsenum']);
        $this->assertSame (interlace ($registry ['system'], 'Helpers'), $registry ['helpers']);
        $this->assertSame ('.helper.php', $registry ['xthelpers']);
        $this->assertSame (interlace ($registry ['system'], 'Middleware'), $registry ['middleware']);
        $this->assertSame ('.middleware.php', $registry ['xtmiddleware']);
        $this->assertSame ('System\\Middleware\\', $registry ['nsmiddleware']);
        $this->assertSame (interlace ($registry ['system'], 'Services'), $registry ['services']);
        $this->assertSame ('.service.php', $registry ['xtservices']);
        $this->assertSame ('System\\Services\\', $registry ['nsservices']);
        $this->assertSame (interlace ($registry ['system'], 'Templates'), $registry ['templates']);
        $this->assertSame (interlace ($registry ['modules'], 'Controllers'), $registry ['controllers']);
        $this->assertSame ('Modules\\Controllers\\', $registry ['nscontrollers']);
        $this->assertSame ('.controller.php', $registry ['xtcontrollers']);
        $this->assertSame (interlace ($registry ['modules'], 'Models'), $registry ['models']);
        $this->assertSame ('Modules\\Models\\', $registry ['nsmodels']);
        $this->assertSame ('.model.php', $registry ['xtmodels']);
        $this->assertSame (interlace ($registry ['modules'], 'Views'), $registry ['views']);
        $this->assertSame ('.view.php', $registry ['xtviews']);
        $this->assertSame (interlace ($registry ['templates'], 'Components'), $registry ['components']);
        $this->assertSame ('.component.php', $registry ['xtcomponents']);
        $this->assertSame (interlace ($registry ['templates'], 'Errors'), $registry ['errors']);
        $this->assertSame ('.error.php', $registry ['xterrors']);
        $this->assertSame (interlace ($registry ['templates'], 'Layouts'), $registry ['layouts']);
        $this->assertSame ('.layout.php', $registry ['xtlayouts']);
        $this->assertSame (interlace ($registry ['templates'], 'Pages'), $registry ['pages']);
        $this->assertSame ('.page.php', $registry ['xtpages']);
        $this->assertSame (interlace ($registry ['assets'], 'css'), $registry ['css']);
        $this->assertSame ('https://example.test/plugin/assets/css', $registry ['css_url']);
        $this->assertSame (interlace ($registry ['assets'], 'images'), $registry ['images']);
        $this->assertSame ('https://example.test/plugin/assets/images', $registry ['images_url']);
        $this->assertSame (interlace ($registry ['assets'], 'js'), $registry ['js']);
        $this->assertSame ('https://example.test/plugin/assets/js', $registry ['js_url']);
        $this->assertStringContainsString ('radykfw-', $registry ['cache']);
        $this->assertStringEndsWith (sep . 'cache', $registry ['cache']);
    }
}
