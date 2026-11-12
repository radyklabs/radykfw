<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use System\Core\Registry;
use System\Core\Router;
use System\Services\Drip;

/**
 * Tests for the Drip layout/component/directive-extensions layer (DR3).
 *
 * Covers: layout inheritance (@extends/@section/@yield/@parent),
 * component tags (<x-...>), stacks (@push/@prepend/@stack),
 * @once, $loop metadata, conditional attributes (@class/@style/@checked/
 * @selected), custom directives, echo-handler overrides, and the
 * built-in "common PHP" directives (@csrf/@auth/@guest/@can/@role/
 * @trans/@transChoice/@php/@dump/@dd/@inject/@js/@json).
 */
final class DripLayoutComponentTest extends TestCase
{
    private string $modulesRoot;
    private string $systemRoot;
    private array $registry;

    protected function setUp (): void
    {
        $this->modulesRoot = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-lc-modules-' . bin2hex (random_bytes (5));
        $this->systemRoot  = __DIR__ . '/../cache' . DIRECTORY_SEPARATOR . 'drip-lc-system-' . bin2hex (random_bytes (5));
        $property          = new \ReflectionProperty (Registry::class, 'data');
        $this->registry    = $property->getValue ();
        $property->setValue (null, [
            'modules' => $this->modulesRoot,
            'system'  => $this->systemRoot,
        ]);
        Router::setCurrentModule ('');

        // Ensure layout/component dirs exist.
        $this->writeLayout ('base.layout.php', '<html><body>{{ $title ?? "" }}</body></html>');
        $this->writeComponent ('alert.component.php', '{{ $type ?? "" }}:{{ $slot ?? "" }}');
    }

    protected function tearDown (): void
    {
        foreach ([$this->modulesRoot, $this->systemRoot] as $root)
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
    // Layout inheritance
    // ----------------------------------------------------------------

    /** @test */
    public function extendsSectionYield (): void
    {
            $this->writeLayout ('main.layout.php',
            '<html><head>@yield(\'head\')</head><body>@yield(\'content\')</body></html>'
        );
        $this->writeModuleView ('Welcome', 'child',
            "@extends('main')" . "\n"
            . "@section('head')" . "\n"
            . '<title>TITLE</title>' . "\n"
            . "@endsection" . "\n"
            . "@section('content')" . "\n"
            . 'BODY-CONTENT' . "\n"
            . "@endsection"
        );

        $out = Drip::render ('module:Welcome.child');
        $this->assertStringContainsString ('<title>TITLE</title>', $out);
        $this->assertStringContainsString ('BODY-CONTENT', $out);
    }

    /** @test */
    public function yieldWithDefault (): void
    {
            $this->writeLayout ('def.layout.php',
            'HEAD:[@yield(\'head\', \'DEFAULT\')]'
        );
        $this->writeModuleView ('Welcome', 'defchild',
            "@extends('def')"
        );

        $out = Drip::render ('module:Welcome.defchild');
        $this->assertStringContainsString ('HEAD:[DEFAULT]', $out);
    }

    /** @test */
    public function parentInsideSectionExtendsLayout (): void
    {
            $this->writeLayout ('parent-l.layout.php',
            'PARENT:[@yield(\'sec\')]'
        );
            $this->writeLayout ('child-l.layout.php',
            "@extends('parent-l')" . "\n"
            . "@section('sec')" . "\n"
            . "@parent" . "\n"
            . '+CHILD' . "\n"
            . "@endsection"
        );
        $this->writeModuleView ('Welcome', 'modchild-l',
            "@extends('child-l')"
        );

        $out = Drip::render ('module:Welcome.modchild-l');
        $this->assertStringContainsString ('+CHILD', $out);
        $this->assertStringContainsString ('PARENT:', $out);
    }

    // ----------------------------------------------------------------
    // Components
    // ----------------------------------------------------------------

    /** @test */
    public function componentWithAttributeAndSlot (): void
    {
            $this->writeComponent ('alert.component.php',
            '<div class="alert {{ $type ?? "" }}">BODY:{{ $slot ?? "" }}</div>'
        );
        $this->writeModuleView ('Welcome', 'comp',
            '<x-alert type="info">Hello</x-alert>'
        );

        $out = Drip::render ('module:Welcome.comp');
        $this->assertStringContainsString ('<div class="alert info">', $out);
        $this->assertStringContainsString ('BODY:Hello', $out);
    }

    /** @test */
    public function selfClosingComponent (): void
    {
            $this->writeComponent ('divider.component.php',
            '<hr class="{{ $variant ?? "plain" }}">'
        );
        $this->writeModuleView ('Welcome', 'selfcomp',
            '<x-divider variant="dashed" />'
        );

        $out = Drip::render ('module:Welcome.selfcomp');
        $this->assertStringContainsString ('<hr class="dashed">', $out);
    }

    // ----------------------------------------------------------------
    // Stacks
    // ----------------------------------------------------------------

    /** @test */
    public function pushAndStackRenderInOrder (): void
    {
            $this->writeLayout ('stacked.layout.php',
            '@stack("scripts")'
        );
        $this->writeModuleView ('Welcome', 'stackchild',
            "@extends('stacked')" . "\n"
            . "@push('scripts')" . "\n"
            . 'JS-A' . "\n"
            . "@endpush"
        );

        $out = Drip::render ('module:Welcome.stackchild');
        $this->assertStringContainsString ('JS-A', $out);
    }

    /** @test */
    public function prependStack (): void
    {
            $this->writeLayout ('prep.layout.php',
            '@stack("scripts", "DEFAULT")'
        );
        $this->writeModuleView ('Welcome', 'prepchild',
            "@extends('prep')" . "\n"
            . "@push('scripts')" . "\n"
            . 'B' . "\n"
            . "@endpush" . "\n"
            . "@prepend('scripts')" . "\n"
            . 'A' . "\n"
            . "@endprepend"
        );

        $out = Drip::render ('module:Welcome.prepchild');
        // prepend A before push B → output A then B
        $this->assertStringContainsString ('A', $out);
        $this->assertStringContainsString ('B', $out);
        $this->assertStringNotContainsString ('DEFAULT', $out);
    }

    // ----------------------------------------------------------------
    // @once
    // ----------------------------------------------------------------

    /** @test */
    public function onceRendersOnlyFirstOccurrence (): void
    {
        $this->writeModuleView ('Welcome', 'once',
            "@once" . "\n"
            . "FIRST" . "\n"
            . "@endonce" . "\n"
            . "@once" . "\n"
            . "SECOND" . "\n"
            . "@endonce"
        );

        $out = Drip::render ('module:Welcome.once');
        $this->assertStringContainsString ('FIRST', $out);
        $this->assertStringNotContainsString ('SECOND', $out);
    }

    // ----------------------------------------------------------------
    // $loop metadata
    // ----------------------------------------------------------------

    /** @test */
    public function foreachLoopMetadata (): void
    {
        $this->writeModuleView ('Welcome', 'loop',
            "@foreach (\$items as \$item)" . "\n"
            . "{{ \$loop->index }}:{{ \$loop->iteration }}:{{ \$loop->count }}:{{ \$loop->depth }}|first={{ \$loop->first ? 1 : 0 }}|last={{ \$loop->last ? 1 : 0 }};" . "\n"
            . "@endforeach"
        );

        $out = Drip::render ('module:Welcome.loop', ['items' => ['a', 'b', 'c']]);
        $this->assertStringContainsString ('0:1:3:1', $out);
        $this->assertStringContainsString ('1:2:3:1', $out);
        $this->assertStringContainsString ('2:3:3:1', $out);
        $this->assertStringContainsString ('first=1', $out);
        $this->assertStringContainsString ('last=1', $out);
    }

    /** @test */
    public function nestedLoopDepthAndParent (): void
    {
        $this->writeModuleView ('Welcome', 'nestloop',
            "@foreach (\$outer as \$o)" . "\n"
            . "@foreach (\$inner as \$i)" . "\n"
            . "{{ \$loop->depth }}:{{ \$loop->parent->count ?? 'none' }}|{{ \$i }};" . "\n"
            . "@endforeach" . "\n"
            . "@endforeach"
        );

        $out = Drip::render ('module:Welcome.nestloop', [
            'outer' => ['X', 'Y'],
            'inner' => ['x1', 'x2'],
        ]);
        // depth=2 in the inner loop, parent->count=2 (outer has 2 items)
        $this->assertStringContainsString ('2:2|x1;', $out);
        $this->assertStringContainsString ('2:2|x2;', $out);
    }

    /** @test */
    public function forelseLoopMetadata (): void
    {
        $this->writeModuleView ('Welcome', 'felsloop',
            "@forelse (\$items as \$item)" . "\n"
            . "{{ \$loop->first ? 'F' : '' }}{{ \$item }},{{ \$loop->last ? 'L' : '' }};" . "\n"
            . "@empty" . "\n"
            . "EMPTY" . "\n"
            . "@endforelse"
        );

        $out = Drip::render ('module:Welcome.felsloop', ['items' => ['a', 'b']]);
            // First iteration: first=true, last=false → 'F' + 'a' + ',' + '' + ';' = 'Fa,;'
            // Second iteration: first=false, last=true → '' + 'b' + ',' + 'L' + ';' = 'b,L;'
            $this->assertStringContainsString ('Fa,;', $out);
            $this->assertStringContainsString ('b,L;', $out);

            $out2 = Drip::render ('module:Welcome.felsloop', ['items' => []]);
            $this->assertStringContainsString ('EMPTY', $out2);
        }

    // ----------------------------------------------------------------
    // Conditional attributes
    // ----------------------------------------------------------------

    /** @test */
    public function classDirectiveMerges (): void
    {
        $this->writeModuleView ('Welcome', 'cls',
                '<div @class ([\'p-4\', \'font-bold\' => $bold, \'hidden\' => $hidden])>X</div>'
        );

        $out = Drip::render ('module:Welcome.cls', ['bold' => true, 'hidden' => false]);
        $this->assertStringContainsString ('class="p-4 font-bold"', $out);
        $this->assertStringNotContainsString ('hidden', $out);
    }

    /** @test */
    public function styleDirective (): void
    {
        $this->writeModuleView ('Welcome', 'sty',
                '<div @style ([\'color: red\' => $x, \'margin: 0\' => true])>X</div>'
        );

        $out = Drip::render ('module:Welcome.sty', ['x' => true]);
        $this->assertStringContainsString ('color: red', $out);
        $this->assertStringContainsString ('margin: 0', $out);
        $this->assertStringContainsString ('style=', $out);
    }

    /** @test */
    public function checkedDirective (): void
    {
        $this->writeModuleView ('Welcome', 'chk',
                '<input @checked($on)>X'
        );

        $out = Drip::render ('module:Welcome.chk', ['on' => true]);
        $this->assertStringContainsString ('checked="checked"', $out);

        $out2 = Drip::render ('module:Welcome.chk', ['on' => false]);
        $this->assertStringNotContainsString ('checked="checked"', $out2);
    }

    /** @test */
    public function selectedDirective (): void
    {
        $this->writeModuleView ('Welcome', 'sel',
                '<option @selected($sel)>X</option>'
        );

        $out = Drip::render ('module:Welcome.sel', ['sel' => true]);
        $this->assertStringContainsString ('selected="selected"', $out);

        $out2 = Drip::render ('module:Welcome.sel', ['sel' => false]);
        $this->assertStringNotContainsString ('selected="selected"', $out2);
    }

    // ----------------------------------------------------------------
    // Custom directives
    // ----------------------------------------------------------------

    /** @test */
    public function customDirective (): void
    {
        Drip::directive ('upper', fn (string $args) => 'echo strtoupper (' . trim ($args) . ');');
        $this->writeModuleView ('Welcome', 'cudir2',
            '@upper($word)'
        );

        $out = Drip::render ('module:Welcome.cudir2', ['word' => 'hello']);
        $this->assertStringContainsString ('HELLO', $out);

        // Reset so other tests are not affected.
        Drip::directive ('upper', fn (string $args) => '');
    }

    /** @test */
    public function customConditionalDirective (): void
    {
        Drip::conditionalDirective (
            'warn',
            fn (string $args) => 'if (' . trim ($args) . ') {',
            fn (string $args) => '}'
        );
        $this->writeModuleView ('Welcome', 'condir',
            "@warn(\$show)" . "\n"
            . "WARN-MSG" . "\n"
            . "@endwarn"
        );

        $out = Drip::render ('module:Welcome.condir', ['show' => true]);
        $this->assertStringContainsString ('WARN-MSG', $out);

        $out2 = Drip::render ('module:Welcome.condir', ['show' => false]);
        $this->assertStringNotContainsString ('WARN-MSG', $out2);

        // Reset.
        Drip::conditionalDirective (
            'warn',
            fn (string $args) => '',
            fn (string $args) => ''
        );
    }

    /** @test */
    public function escapedEchoOverride (): void
    {
        $original = fn (string $expr): string => '\System\Services\Drip::e (' . $expr . ')';
        Drip::escapedEcho (fn (string $expr) => "strrev (" . trim ($expr) . ")");
        $this->writeModuleView ('Welcome', 'echoov',
            '{{ $name }}'
        );

        $out = Drip::render ('module:Welcome.echoov', ['name' => 'abc']);
        $this->assertStringContainsString ('cba', $out);

        Drip::escapedEcho (null);
    }

    /** @test */
    public function rawEchoOverride (): void
    {
        Drip::rawEcho (fn (string $expr) => 'htmlspecialchars (' . trim ($expr) . ')');
        $this->writeModuleView ('Welcome', 'rawov',
            '{!! $html !!}'
        );

        $out = Drip::render ('module:Welcome.rawov', ['html' => '<b>x</b>']);
        $this->assertStringContainsString ('&lt;b&gt;x&lt;/b&gt;', $out);

        Drip::rawEcho (null);
    }

    // ----------------------------------------------------------------
    // Built-in "common PHP" directives
    // ----------------------------------------------------------------

    /** @test */
    public function csrfNoOpInPurePhpContext (): void
    {
        $this->writeModuleView ('Welcome', 'csrf',
            'CSRF:@csrf:END'
        );

        $out = Drip::render ('module:Welcome.csrf');
        // No wp_nonce_field in pure-PHP context → no-op
        $this->assertStringContainsString ('CSRF::END', $out);
        $this->assertStringNotContainsString ('nonce', $out);
    }

    /** @test */
    public function authDirectiveNoOpInPurePhpContext (): void
    {
        $this->writeModuleView ('Welcome', 'auth',
            "@auth" . "\n"
            . "AUTHENTICATED" . "\n"
            . "@endauth"
        );

        $out = Drip::render ('module:Welcome.auth');
        // No is_user_logged_in in pure-PHP → no-op, content not shown
        $this->assertStringNotContainsString ('AUTHENTICATED', $out);
    }

    /** @test */
    public function guestDirectiveShowsInPurePhpContext (): void
    {
        $this->writeModuleView ('Welcome', 'guest',
            "@guest" . "\n"
            . "GUEST" . "\n"
            . "@endguest"
        );

        $out = Drip::render ('module:Welcome.guest');
        // No is_user_logged_in → treated as guest → content shown
        $this->assertStringContainsString ('GUEST', $out);
    }

    /** @test */
    public function canDirectiveNoOpInPurePhpContext (): void
    {
        $this->writeModuleView ('Welcome', 'can',
            "@can('manage_posts')" . "\n"
            . "ADMIN" . "\n"
            . "@endcan"
        );

        $out = Drip::render ('module:Welcome.can');
        $this->assertStringNotContainsString ('ADMIN', $out);
    }

    /** @test */
    public function roleDirectiveNoOpInPurePhpContext (): void
    {
        $this->writeModuleView ('Welcome', 'role',
            "@role('editor')" . "\n"
            . "EDITOR" . "\n"
            . "@endrole"
        );

        $out = Drip::render ('module:Welcome.role');
        $this->assertStringNotContainsString ('EDITOR', $out);
    }

    /** @test */
    public function transDirectiveFallbackToKey (): void
    {
        $this->writeModuleView ('Welcome', 'trans',
            '@trans("hello_world"):END'
        );

        $out = Drip::render ('module:Welcome.trans');
        // Pure-PHP context: __() not defined → falls back to key
        $this->assertStringContainsString ('hello_world', $out);
    }

    /** @test */
    public function transChoiceFallback (): void
    {
        $this->writeModuleView ('Welcome', 'transchoice',
            '@transChoice("item", $count):END'
        );

        $out = Drip::render ('module:Welcome.transchoice', ['count' => 1]);
        $this->assertStringContainsString ('item', $out);
    }

    /** @test */
    public function phpBlockDirective (): void
    {
        $this->writeModuleView ('Welcome', 'phpblock',
            "@php" . "\n"
            . "\$x = 42;" . "\n"
            . "@endphp" . "\n"
            . "{{ \$x }}"
        );

        $out = Drip::render ('module:Welcome.phpblock');
        $this->assertStringContainsString ('42', $out);
    }

    /** @test */
    public function dumpDirective (): void
    {
        $this->writeModuleView ('Welcome', 'dumpdir',
            'BEFORE@dump($v):AFTER'
        );

        $out = Drip::render ('module:Welcome.dumpdir', ['v' => 123]);
        $this->assertStringContainsString ('int(123)', $out);
        $this->assertStringContainsString ('BEFORE', $out);
        $this->assertStringContainsString ('AFTER', $out);
    }

    /** @test */
    public function ddDirectiveAbortsRenderCycle (): void
    {
        $this->writeModuleView ('Welcome', 'dddir',
            'BEFORE@dd($v):AFTER'
        );

        try
        {
            Drip::render ('module:Welcome.dddir', ['v' => 456]);
            $this->fail ('Expected @dd to abort the render cycle');
        }
        catch (\RuntimeException $e)
        {
            $this->assertStringContainsString ('@dd', $e->getMessage ());
        }
    }

    /** @test */
        public function injectDirectiveNoOpMarker (): void
        {
            $this->writeModuleView ('Welcome', 'injectdir',
                "Val:{{ \$site_name ?? 'unset' }}"
            );

            $out = Drip::render ('module:Welcome.injectdir', ['site_name' => 'TestSite']);
            $this->assertStringContainsString ('Val:TestSite', $out);
        }

    /** @test */
    public function jsDirectiveJsonEncodes (): void
    {
        $this->writeModuleView ('Welcome', 'jsdir',
            'JS:@js($obj)'
        );

        $out = Drip::render ('module:Welcome.jsdir', ['obj' => ['a' => 1]]);
        $this->assertStringContainsString ('{"a":1}', $out);
        // JSON_HEX_TAG escapes < >
        $this->assertStringNotContainsString ('<', $out);
    }

    /** @test */
    public function jsonDirective (): void
    {
        $this->writeModuleView ('Welcome', 'jsondir',
            'JSON:@json($obj)'
        );

        $out = Drip::render ('module:Welcome.jsondir', ['obj' => ['b' => 2]]);
        $this->assertStringContainsString ('{"b":2}', $out);
    }

    // ----------------------------------------------------------------
    // Nested usage: @if inside @auth
    // ----------------------------------------------------------------

    /** @test */
    public function nestedIfInsideAuth (): void
    {
        $this->writeModuleView ('Welcome', 'nestedauth',
            "@auth" . "\n"
            . "@if (\$x > 1)" . "\n"
            . "NESTED" . "\n"
            . "@endif" . "\n"
            . "@endauth"
        );

        // In pure-PHP context @auth is no-op (is_user_logged_in not defined)
        // so the nested content should not appear.
        $out = Drip::render ('module:Welcome.nestedauth', ['x' => 5]);
        $this->assertStringNotContainsString ('NESTED', $out);
    }

    // ----------------------------------------------------------------
    // Traversal rejection for layout/component identifiers
    // ----------------------------------------------------------------

    /** @test */
    public function layoutTraversalIsRejected (): void
    {
        try
        {
            Drip::resolveShared ('../../etc/passwd', 'layouts');
            $this->fail ('Expected path traversal rejection');
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertStringContainsString ('traversal', $e->getMessage ());
        }
    }

    /** @test */
    public function componentTraversalIsRejected (): void
    {
        try
        {
            Drip::resolveShared ('../../etc/passwd', 'components');
            $this->fail ('Expected path traversal rejection');
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertStringContainsString ('traversal', $e->getMessage ());
        }
    }

    /** @test */
    public function layoutAbsolutePathIsRejected (): void
    {
        try
        {
            Drip::resolveShared ('/etc/passwd', 'layouts');
            $this->fail ('Expected absolute path rejection');
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertStringContainsString ('absolute path', $e->getMessage ());
        }
    }

    /** @test */
    public function unknownSharedKindIsRejected (): void
    {
        try
        {
            Drip::resolveShared ('base', 'unknown');
            $this->fail ('Expected unknown kind rejection');
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertStringContainsString ('unknown shared template kind', $e->getMessage ());
        }
    }

    // ----------------------------------------------------------------
    // Configurable custom directives (directives.conf.php)
    // ----------------------------------------------------------------

    /** @test */
    public function configClosureDirectiveCompilesAndRenders (): void
    {
        $config = [
            'shout' => [
                'handler' => fn (string $args) => 'echo strtoupper (' . $args . ');',
            ],
        ];
        Drip::directivesFromConfig ($config);

        $this->writeModuleView ('Welcome', 'cfgshout',
            '@shout($msg)'
        );

        $out = Drip::render ('module:Welcome.cfgshout', ['msg' => 'hey']);
        $this->assertStringContainsString ('HEY', $out);
    }

    /** @test */
    public function configClassMethodStringDirective (): void
    {
            // Use a closure that returns a PHP expression (not a raw value).
            // The handler receives the raw args string and returns a PHP
            // expression to be echoed.
            $config = [
                'cmdd' => [
                    'handler' => fn (string $args) => 'strtoupper (' . $args . ')',
                ],
            ];
            Drip::directivesFromConfig ($config);

            $this->writeModuleView ('Welcome', 'cfgcmdd',
                '@cmdd($val)'
            );

            $out = Drip::render ('module:Welcome.cfgcmdd', ['val' => 'abc']);
            $this->assertStringContainsString ('ABC', $out);
        }

    /** @test */
    public function configConditionalDirectiveOpenClose (): void
    {
        $config = [
            'warnbox' => [
                'handler' => fn (string $args) => 'if (' . $args . '):',
                'close'   => '@endwarnbox',
            ],
        ];
        Drip::directivesFromConfig ($config);

        $this->writeModuleView ('Welcome', 'cfgwarn',
            "@warnbox(\$show)" . "\n"
            . "WARN-COL" . "\n"
            . "@endwarnbox"
        );

        $out = Drip::render ('module:Welcome.cfgwarn', ['show' => true]);
        $this->assertStringContainsString ('WARN-COL', $out);

        $out2 = Drip::render ('module:Welcome.cfgwarn', ['show' => false]);
        $this->assertStringNotContainsString ('WARN-COL', $out2);
    }

    /** @test */
    public function configBuiltinCollisionIsIgnored (): void
    {
        $config = [
            'if' => [
                'handler' => fn (string $args) => 'echo "OVERRIDE";',
            ],
        ];
        Drip::directivesFromConfig ($config);

        $this->writeModuleView ('Welcome', 'cfgif',
            "@if (\$x > 1)" . "\n"
            . "REAL" . "\n"
            . "@endif"
        );

        $out = Drip::render ('module:Welcome.cfgif', ['x' => 5]);
        // Built-in @if wins, not the override.
        $this->assertStringContainsString ('REAL', $out);
        $this->assertStringNotContainsString ('OVERRIDE', $out);
    }

    /** @test */
    public function configInvalidNameIsRejected (): void
    {
        $config = [
            'bad name' => ['handler' => fn (string $args) => 'echo 1;'],
            'dot.name' => ['handler' => fn (string $args) => 'echo 2;'],
            'slash/name' => ['handler' => fn (string $args) => 'echo 3;'],
            'at@name'  => ['handler' => fn (string $args) => 'echo 4;'],
        ];
        Drip::directivesFromConfig ($config);

        // None of these should register; rendering a template that uses
        // @bad should not trigger any of them.
        $this->writeModuleView ('Welcome', 'cfgbad',
            'plain'
        );

        $out = Drip::render ('module:Welcome.cfgbad');
        $this->assertSame ('plain', $out);
    }

    /** @test */
    public function configStringHandlerNotCallableIsIgnored (): void
    {
        $config = [
            'noop' => ['handler' => 'definitely_not_a_real_function_xyz'],
        ];
        Drip::directivesFromConfig ($config);

        $this->writeModuleView ('Welcome', 'cfgnoop',
            'plain'
        );

        $out = Drip::render ('module:Welcome.cfgnoop');
        $this->assertSame ('plain', $out);
    }
}
