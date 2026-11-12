<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Drip — template engine service.
 *
 * DR1: API, resolvers, renderer seam.
 * DR2: Blade-inspired compiler (control-flow directives, echo tokens, @include, @verbatim).
 * DR3: Layouts, components, stacks, and directive extensions.
 * DR5: Compiled-cache layer + security (path-traversal rejection, clean error messages).
 *
 * DR3 extensibility API (static, process-lifetime; reset between renders):
 *  Drip::directive('name', fn (string $args) => string)        — @name($args)
 *  Drip::conditionalDirective('name', $open, $close)           — @name($args) … @endname($args)
 *  Drip::escapedEcho (?callable) / Drip::rawEcho (?callable)   — override {{ }} / {!! !!} compilation
 *  Drip::resolveShared('base', 'layouts')                       — layout/component identifier -> path
 *  Drip::directivesFromConfig (array $config)                   — load directives from directives.conf.php
 *
 * Drip directives config (`system/Config/directives.conf.php`):
 *   Optional config file returning an array of custom directive definitions.
 *   Load it with `Drip::directivesFromConfig (get_config ('directives', []))`.
 *
 *   Schema per entry:
 *     [
 *         'name' => [
 *             'handler' => callable|string,   // closure, global fn, or 'Class::method'
 *             'close'   => '@endname',        // optional; present = open/close pair; omit = single-line
 *         ],
 *     ]
 *
 *   - `handler` receives the raw args string and returns a PHP statement
 *     to splice into the compiled template. For single-line echo
 *     directives: `return 'echo strtoupper ($args);'`. For open/close
 *     directives: `return 'if ($args):'` (close is always `endif;`).
 *   - `close` is the close-directive name (with or without `@`). If
 *     omitted, the default is `@end{name}`.
 *   - Names colliding with built-in directives are silently ignored.
 *   - Invalid names (spaces, `@`, `.`, `/`, `\`, etc.) are skipped.
 *
 * Identifiers are logical, never filesystem paths:
 *   module: file.drip             -> modules/{Module}/Views/file.view.php
 *   system: pages.dashboard.drip  -> system/Templates/pages/dashboard.page.php
 *   error:  404.drip              -> system/Templates/errors/404.error.html
 *
 * Dot-notation maps to subfolders ('partials.card' -> Views/partials/card.view.php);
 * a Capitalized first segment selects an explicit module — the same rule as
 * resolve_module_view(). The '.drip' suffix selects the Drip renderer in the
 * view helpers; the physical file extension never changes.
 *
 * Absolute paths, '..' traversal and null bytes are rejected. A missing
 * template throws with the logical identifier only — never a filesystem path,
 * and never a silent fallback to the 404 template.
 */
final class Drip
{
    /** Compiler version — bump to force recompilation of all cached files. */
    private const COMPILER_VERSION = '2';

    /** Registered custom directives: name => callable(string $args): string (PHP/text). */
    private static array $directives = [];

    /** Registered conditional directive pairs: name => [open, close] callables. */
    private static array $conditionalDirectives = [];

    /** Custom escaped-echo handler: callable(string $expr): string (PHP to emit). */
    private static $escapedEcho = null;

    /** Custom raw-echo handler: callable(string $expr): string (PHP to emit). */
    private static $rawEcho = null;

    private function __construct () {}

    /**
     * HTML-escape a value for safe interpolation into templates. Uses
     * esc_html() in a real WordPress runtime; otherwise a pure fallback
     * with ENT_QUOTES | ENT_SUBSTITUTE and UTF-8.
     */
    public static function e (string|int|float|null $value): string
    {
        if (function_exists ('esc_html'))
        {
            return esc_html ((string) $value);
        }

        return htmlspecialchars ((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Resolve a kind-prefixed identifier and render it, returning output.
     *
     * @param string $identifier 'module:file.drip' | 'system:pages.dashboard.drip' | 'error:404.drip'
     * @param array  $data       Extracted into the render scope (EXTR_SKIP)
     * @throws \InvalidArgumentException Unknown kind prefix or unsafe identifier
     * @throws \RuntimeException Template missing or compile/execute error
     */
    public static function render (string $identifier, array $data = []): string
    {
        if (str_starts_with ($identifier, 'module:'))
        {
            $file = self::resolveModule ($identifier);
        }
        elseif (str_starts_with ($identifier, 'system:'))
        {
            $file = self::resolveSystem ($identifier);
        }
        elseif (str_starts_with ($identifier, 'error:'))
        {
            $file = self::resolveError ($identifier);
        }
        else
        {
            throw new \InvalidArgumentException (
                "Drip: template identifier must start with 'module:', 'system:' or 'error:': '{$identifier}'"
            );
        }

        return self::execute ($file, $identifier, $data);
    }

    /**
     * Resolve a module view identifier to an absolute path.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveModule (string $identifier): string
    {
        $id      = self::normalize ($identifier);
        $kind    = 'module view';
        self::guard ($id, $kind, $identifier);

        $parts   = explode ('.', $id);
        $current = \current_module ();

        if (count ($parts) >= 2)
        {
            $first     = $parts [0];
            $viewName  = end ($parts);
            $explicit  = ctype_upper ($first [0]);
            $module    = $explicit ? $first : $current;

            $subfolder = $explicit
                ? (count ($parts) > 2 ? implode ('/', array_slice ($parts, 1, -1)) : '')
                : implode ('/', array_slice ($parts, 0, -1));

            if ($module)
            {
                $file = \module_path ($module) . '/Views/'
                    . ($subfolder ? $subfolder . '/' : '') . $viewName . '.view.php';
                if (is_file ($file)) {return $file;}
            }
        }
        elseif (($current = \current_module ()))
        {
            $file = \module_path ($current) . '/Views/' . $parts [0] . '.view.php';
            if (is_file ($file)) {return $file;}
        }

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    /**
     * Resolve a system template identifier to an absolute path.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveSystem (string $identifier): string
    {
        $id   = self::normalize ($identifier);
        $kind = 'system template';
        self::guard ($id, $kind, $identifier);

        $forms = [$id];
        if (! str_contains ($id, '/'))
        {
            $converted = str_replace ('.', '/', $id);
            if ($converted !== $id) {$forms [] = $converted;}
        }

        foreach ($forms as $form)
        {
            foreach (['.page.php', '.html', '.php'] as $extension)
            {
                $file = \system_path ('Templates/' . $form . $extension);
                if (is_file ($file)) {return $file;}
            }
        }

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    /**
     * Resolve an error template identifier to an absolute path.
     *
     * @throws \InvalidArgumentException Invalid or unsafe identifier
     * @throws \RuntimeException Template missing (logical identifier only)
     */
    public static function resolveError (string $identifier): string
    {
        $id   = self::normalize ($identifier);
        $kind = 'error template';
        self::guard ($id, $kind, $identifier);

        $file = \system_path ("Templates/errors/{$id}.error.html");
        if (is_file ($file)) {return $file;}

        throw new \RuntimeException ("Drip: template not found: '{$id}' ({$kind})");
    }

    // ----------------------------------------------------------------
    // DR3 — shared layout/component resolution, extensibility API
    // ----------------------------------------------------------------

    /**
     * Resolve a shared layout or component identifier to an absolute path.
     *
     * Dot-notation maps to subfolders under `system/Templates/{kind}/`.
     * Extension is appended when the identifier has none.
     *
     * @param string $identifier e.g. 'base' or 'forms.input'
     * @param string $kind       'layouts' or 'components'
     * @throws \InvalidArgumentException Unsafe identifier
     * @throws \RuntimeException File not found
     */
    public static function resolveShared (string $identifier, string $kind): string
    {
        if (! in_array ($kind, ['layouts', 'components'], true))
        {
            throw new \InvalidArgumentException ("Drip: unknown shared template kind: '{$kind}'");
        }

        $id   = self::normalize ($identifier);
        $kindLabel = "shared {$kind} template";
        self::guard ($id, $kindLabel, $identifier);

        $base = \system_path ('Templates/' . $kind);

        $candidates = [];
        if (! str_contains ($id, '/'))
        {
            $converted = str_replace ('.', '/', $id);
            if ($converted !== $id) {$candidates [] = $converted;}
        }
        $candidates [] = $id;

        foreach ($candidates as $relative)
        {
            $extensions = str_ends_with ($relative, '.html') ? ['.html'] : ['.page.php', '.php'];

            foreach ($extensions as $extension)
            {
                $file = $base . '/' . $relative . $extension;
                if (is_file ($file)) {return $file;}
            }
        }

        throw new \RuntimeException ("Drip: shared template not found: '{$identifier}' ({$kindLabel})");
    }

    /**
     * Render a shared layout or component file with data.
     * Data keys are extracted into the scope (EXTR_SKIP); the caller's
     * own variables stay available to the rendered template.
     *
     * @param string $file Absolute path from resolveShared()
     * @param array  $data Extracted into the render scope
     */
    public static function renderShared (string $file, array $data = []): string
    {
        $identifier = basename ($file);
        return self::execute ($file, $identifier, $data);
    }

    /**
     * Register a custom directive: `@name(args)` is compiled via the callable.
     *
     * @param string   $name Directive name (used as `@name`)
     * @param callable $fn   Receives the raw argument string (inside the parens),
     *                       returns a PHP statement to splice in place.
     *                       Use `echo <expr>;` for output-producing directives.
     */
    public static function directive (string $name, callable $fn): void
    {
        self::$directives [strtolower ($name)] = $fn;
    }

    /**
     * Register a custom conditional directive: `@name(args) ... @endname(args)`.
     *
     * @param string   $name  Base name; open is `@name`, close is `@end{name}`
     * @param callable $open  Receives the raw open-args string, returns a PHP
     *                        statement (typically `if (<expr>): `)
     * @param callable $close Receives the raw close-args string, returns a PHP
     *                        statement (typically `endif;`)
     */
    public static function conditionalDirective (string $name, callable $open, callable $close): void
    {
        $name = strtolower ($name);
        self::$directives [$name]          = $open;
        self::$directives ['end' . $name]  = $close;
        self::$conditionalDirectives [$name] = [$open, $close];
    }

    /**
     * Load custom directives from a config array.
     *
     * Accepts the shape returned by `system/Config/directives.conf.php`:
     *
     * ```php
     * [
     *     // single-line: @mydir($x) → handler('1') → 'echo strtoupper (1);'
     *     'mydir' => [
     *         'handler' => fn (string $args) => 'echo strtoupper (' . $args . ');',
     *     ],
     *
     *     // conditional: @warn($cond) … @endwarn
     *     // The handler receives the open-args and returns the PHP open
     *     // statement; the close is automatically `endif;` (or the custom
     *     // string given in 'close').
     *     'warn' => [
     *         'handler' => fn (string $args) => 'if (' . $args . '):',
     *         'close'   => '@endwarn',   // optional; default is @end{name}
     *     ],
     *
     *     // Class-method string handler
     *     'myother' => [
     *         'handler' => 'MyNamespace\MyClass::myMethod',
     *     ],
     * ]
     *
     * - `handler` is a callable: a closure, a global function name string,
     *   or a `Class::method` string. Closures may be defined directly in
     *   the config .php file.
     * - `close` is the close-directive name (with or without `@`); omit
     *   it to use the default `@end{name}`. Only present for open/close
     *   pairs; its absence makes the directive single-line.
     * - Names that collide with built-in directives are silently ignored;
     *   built-ins always take precedence and are never overridden.
     * - Invalid names (non-identifier characters, spaces, `@`, `.`, `/`, `\`)
     *   are skipped.
     *
     * Call this method after the Registry/system is bootstrapped and
     * before the first Drip template compile. The service does NOT
     * auto-load the config file; the caller must invoke it with the
     * array returned by `get_config('directives', [])`.
     *
     * @param array $config Array shape as above.
     */
    public static function directivesFromConfig (array $config): void
    {
        $builtins = self::builtinDirectiveNames ();

        foreach ($config as $name => $entry)
        {
            if (! is_string ($name) || ! preg_match ('/^[a-z][a-z0-9_]*$/i', $name)) {continue;}
            if (in_array ($name, $builtins, true)) {continue;}

            if (! is_array ($entry) || ! isset ($entry ['handler'])) {continue;}

            $handler = $entry ['handler'];

            // Resolve string handlers (function name or Class::method) to callables.
            if (is_string ($handler))
            {
                if (! is_callable ($handler)) {continue;}
                $target = $handler;
                $handler = function (string $args) use ($target): string
                {
                    $result = $target ($args);
                    return is_string ($result) ? $result : '';
                };
            }
            elseif (! is_callable ($handler)) {continue;}

            $openFn = $handler;

            if (isset ($entry ['close']))
            {
                $closeName = str_replace ('@', '', trim ($entry ['close']));
                if ($closeName === '') {$closeName = 'end' . $name;}

                $closeFn = fn (string $args): string => 'endif;';

                self::$directives [$name] = $openFn;
                self::$directives [$closeName] = $closeFn;
                self::$conditionalDirectives [$name] = [$openFn, $closeFn];
            }
            else
            {
                self::$directives [$name] = $openFn;
            }
        }
    }

    /**
     * Return the set of built-in directive names that config entries
     * must not override.
     *
     * @return list<string>
     */
    private static function builtinDirectiveNames (): array
    {
        return [
            'if', 'elseif', 'else', 'endif',
            'unless', 'endunless',
            'isset', 'endisset',
            'empty', 'endempty',
            'for', 'endfor',
            'foreach', 'endforeach',
            'forelse', 'endforelse',
            'while', 'endwhile',
            'switch', 'endswitch',
            'case', 'break', 'continue', 'default',
            'include', 'verbatim', 'endverbatim',
            'loop',
            'class', 'style', 'checked', 'selected',
            'csrf',
            'auth', 'endauth', 'guest', 'endguest',
            'can', 'endcan',
            'role', 'endrole',
            'trans', 'transChoice',
            'php', 'endphp',
            'dump', 'dd',
            'inject',
            'js', 'json',
            'extends', 'section', 'endsection', 'yield', 'parent',
            'push', 'endpush', 'prepend', 'endprepend', 'stack',
            'once', 'endonce',
        ];
    }
    /**
     * Override default escaped-echo (`{{ ... }}`) compilation.
     *
     * @param callable|null $fn Receives the raw expression, returns PHP to emit.
     *                          null restores the default (`Drip::e()` wrap).
     */
    public static function escapedEcho (?callable $fn): void
    {
        self::$escapedEcho = $fn;
    }

    /**
     * Override default raw-echo (`{!! ... !!}`) compilation.
     *
     * @param callable|null $fn Receives the raw expression, returns PHP to emit.
     *                          null restores the default (bare expression).
     */
    public static function rawEcho (?callable $fn): void
    {
        self::$rawEcho = $fn;
    }

    // ----------------------------------------------------------------
    // Compiler (DR2 + DR3)
    // ----------------------------------------------------------------

    /**
     * Compile Drip template source into executable PHP.
     *
     * Pipeline (order matters):
     *  1. Mask native-PHP blocks with sentinels.
     *  2. Strip comments ({{-- --}}) — before echo/directive compilation.
     *  3. Compile @verbatim blocks (content becomes raw PHP, protected).
     *  4. Compile layout inheritance directives (@extends, @section, @yield, @parent).
     *  5. Compile stacks directives (@push, @prepend, @stack).
     *  6. Compile @once blocks.
     *  7. Compile component tags (<x-...>).
     *  8. Compile echo tokens ({{ }}, {!! !!}) — outside verbatim.
     *  9. Compile @directive tokens via if/elseif chain + custom directives.
     * 10. Splice native-PHP blocks back.
     * 11. Strip newlines adjacent to PHP tag boundaries.
     */
    private static function compile (string $source): string
    {
        // 1. Mask native-PHP blocks.
        $phpBlocks = [];
        $masked    = preg_replace_callback (
            '#<\?(?:php|=\s?)(.*?)\?>#s',
            function (array $m) use (&$phpBlocks): string
            {
                $phpBlocks [] = $m [0];
                return "\x00PHPBLOCK" . (count ($phpBlocks) - 1) . "\x00";
            },
            $source
        );
        if ($masked === null) {$masked = $source;}

        // 2. Comments — stripped before echo/directive compilation so
        //    {{-- --}} is never treated as a {{ }} expression.
        $masked = self::compileComments ($masked);

        // 3. Verbatim blocks — content wrapped in PHP tag pair, protected
        //    from subsequent echo-token compilation.
        $masked = self::compileVerbatim ($masked);

        // 4. Layout inheritance: @extends, @section, @endsection, @yield, @parent.
        //    Sections are captured into arrays at runtime; @extends triggers
        //    a re-render of the parent layout with the captured sections.
        $masked = self::compileLayoutDirectives ($masked);

        // 5. Stacks: @push, @prepend, @stack, @endpush, @endprepend.
        $masked = self::compileStackDirectives ($masked);

        // 6. @once blocks — content rendered only once per render cycle.
        $masked = self::compileOnceDirectives ($masked);

        // 7. Component tags: <x-alert type="info">...</x-alert> and <x-forms.input />.
        $masked = self::compileComponentTags ($masked);

        // 8. Echo tokens (outside verbatim — verbatim content is now in
        //    PHP string context, safe from the echo regex).
        $masked = self::compileEchoTokens ($masked);

        // 9. Directives (built-in + custom).
        $masked = self::compileDirectives ($masked);

        // 10. Splice native-PHP blocks back.
        $masked = preg_replace_callback (
            '#\x00PHPBLOCK(\d+)\x00#',
            function (array $m) use ($phpBlocks): string
            {
                $idx = (int) $m [1];
                return isset ($phpBlocks [$idx]) ? $phpBlocks [$idx] : '';
            },
            $masked
        ) ?? $masked;

        // 11. Strip newlines that are adjacent to PHP tag boundaries so that
        //    directive-only lines don't leak extra \n into the output.
        $masked = preg_replace ('/(?<=\?>)\n/', '', $masked);
        $masked = preg_replace ('/\n(?=<\?php)/', '', $masked);

        return $masked;
    }

    /**
     * Strip {{-- comment --}} blocks (multi-line safe).
     * An optional trailing \n is consumed so a comment on its own line
     * joins the surrounding lines cleanly.
     */
    private static function compileComments (string $source): string
    {
        return preg_replace ('#{{--.*?--\}\}\n?#s', '', $source) ?? $source;
    }

    /**
     * Compile @verbatim ... @endverbatim blocks.
     *
     * Content is wrapped in a PHP tag pair so it executes as raw PHP.
     * Any template echo tokens ({{ }}, {!! !!}) inside are emitted as
     * literal string echoes so they print without being compiled.
     * The brace pairs are split across string-concat segments so the
     * compileEchoTokens regex (which runs afterwards) cannot match them.
     */
    private static function compileVerbatim (string $source): string
    {
        return preg_replace_callback (
            '#@verbatim\s*(.*?)@endverbatim#s',
            function (array $m): string
            {
                $content = $m [1];
                $open   = "\x3C?php ";
                $close  = "?\x3E";

                // No template tokens — wrap the whole block in a PHP tag pair.
                if (! (str_contains ($content, '{{') || str_contains ($content, '{!!')))
                {
                    $trimmed = trim ($content);
                    return $open . $trimmed . $close;
                }

                // Template tokens present — split and handle each part.
                $parts = preg_split (
                    '#(\{\{.*?\}\}|\{!!.*?!!\})#s',
                    $content,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
                );
                if ($parts === false) {return $open . trim ($content) . $close;}

                $out = '';
                foreach ($parts as $part)
                {
                    if (str_starts_with ($part, '{{') || str_starts_with ($part, '{!!'))
                    {
                        // Split the outer braces so compileEchoTokens cannot match.
                        // e.g. {{ $name }} → echo '{' . '{ $name }' . '}';
                        $inner = trim ($part);
                        // Remove one leading { and one trailing } (or !!}).
                        $lead = $inner [0]; // '{'
                        $body = substr ($inner, 1);
                        if (str_ends_with ($body, '!!}')) {$trail = '!!}'; $body = substr ($body, 0, -3);}
                        else {$trail = '}'; $body = substr ($body, 0, -1);}

                        $bodyEsc = str_replace ('\\', '\\\\', $body);
                        $bodyEsc = str_replace ('\'', '\\\'', $bodyEsc);
                        $out   .= "echo '{$lead}' . '{$bodyEsc}' . '{$trail}';" . "\n";
                    }
                    else
                    {
                        $chunk = $out === '' ? ltrim ($part, "\n\r") : $part;
                        $out  .= $chunk;
                    }
                }

                return $open . rtrim ($out, "\n") . $close;
            },
            $source
        ) ?? $source;
    }

    /**
     * Compile layout inheritance directives: @extends, @section, @endsection,
     * @yield, @parent.
     *
     * Sections are captured at runtime into $__drip_sections; @extends is
     * compiled to a re-render of the parent layout with the captured
     * sections injected into the parent's data array.
     */
    private static function compileLayoutDirectives (string $source): string
    {
        $o = "\x3C?php ";
        $c = "?\x3E";

        // @extends('layouts.base') — emit a runtime marker. execute()
        // scans the compiled buffer output for the marker, splits the
        // pre/post buffers, and renders the layout with captured
        // sections/stacks (first occurrence wins).
        $source = preg_replace_callback (
            '/@extends\s*\(\s*([\'"])([^\'"]+)\1\s*\)\n?/',
            function (array $m) use ($o, $c): string
            {
                $id = $m [2];
                $idEsc = str_replace ('\\', '\\\\', $id);
                $idEsc = str_replace ("\x00", '', $idEsc);
                return $o . 'echo "\x00DRIPEXT\x00' . $idEsc . '\x00"; ' . $c;
            },
            $source
        ) ?? $source;

        // @section('name') ... @endsection
        $source = preg_replace_callback (
            '/@section\s*\(\s*([\'"])([^\'"]+)\1\s*\)\n?/',
            function (array $m) use ($o, $c): string
            {
                $name = $m [2];
                return $o . "\$__drip_section_key = '{$name}'; ob_start (); " . $c;
            },
            $source
        ) ?? $source;

        // @section('name') @show — inline form: capture nothing.
        // (Not supported; use @yield with default instead.)

        $source = preg_replace (
            '/@endsection\s*\n?/',
                    $o . "\$__drip_sections [\$__drip_section_key] = ob_get_clean (); unset (\$__drip_section_key); " . $c,
            $source
        ) ?? $source;

        // @yield('name') or @yield('name', 'default')
        $source = preg_replace_callback (
            '/@yield\s*\(\s*([\'"])([^\'"]+)\1\s*(?:,\s*([\'"])([^\'"]*)\3)?\s*\)/',
            function (array $m) use ($o, $c): string
            {
                $key = $m [2];
                $hasDefault = isset ($m [4]);
                $defaultExpr = $hasDefault ? $m [3] . $m [4] . $m [3] : "''";
                return $o
                    . "echo array_key_exists ('{$key}', \$__drip_sections) ? \$__drip_sections ['{$key}'] : "
                    . $defaultExpr . '; '
                    . $c;
            },
            $source
        ) ?? $source;

        // @parent inside a section — emit the parent's value for this section.
        $source = preg_replace (
            '/@parent\s*\n?/',
            $o . "echo array_key_exists (\$__drip_section_key, \$__drip_parent_sections ?? []) ? "
                . "\$__drip_parent_sections [\$__drip_section_key] : ''; "
                . $c,
            $source
        ) ?? $source;

        return $source;
    }

    /**
     * Compile stack directives: @push, @prepend, @stack, @endpush, @endprepend.
     * Stacks are accumulated in $__drip_stacks at runtime.
     */
    private static function compileStackDirectives (string $source): string
    {
        $o = "\x3C?php ";
        $c = "?\x3E";

        $source = preg_replace_callback (
            '/@push\s*\(\s*([\'"])([^\'"]+)\1\s*\)\n?/',
            function (array $m) use ($o, $c): string
            {
                $name = $m [2];
                return $o . "\$__drip_stack_key = '{$name}'; ob_start (); " . $c;
            },
            $source
        ) ?? $source;

        // @push('name') / @prepend('name') capture the block; @endpush /
        // @endprepend store it in $__drip_stacks.
        $source = preg_replace (
            '/@endpush\s*\n?/',
            $o . "\$__drip_stacks [\$__drip_stack_key] ??= [];"
                . " array_push (\$__drip_stacks [\$__drip_stack_key], ob_get_clean ()); "
                . "unset (\$__drip_stack_key); " . $c,
            $source
        ) ?? $source;

        $source = preg_replace_callback (
            '/@prepend\s*\(\s*([\'"])([^\'"]+)\1\s*\)\n?/',
            function (array $m) use ($o, $c): string
            {
                $name = $m [2];
                return $o . "\$__drip_stack_key = '{$name}'; ob_start (); " . $c;
            },
            $source
        ) ?? $source;

        $source = preg_replace (
            '/@endprepend\s*\n?/',
            $o . "\$__drip_stacks [\$__drip_stack_key] ??= [];"
                . " array_unshift (\$__drip_stacks [\$__drip_stack_key], ob_get_clean ()); "
                . "unset (\$__drip_stack_key); " . $c,
            $source
        ) ?? $source;

        // @stack('name') or @stack('name', 'default')
        $source = preg_replace_callback (
            '/@stack\s*\(\s*([\'"])([^\'"]+)\1\s*(?:,\s*([\'"])([^\'"]*)\3)?\s*\)/',
            function (array $m) use ($o, $c): string
            {
                $key = $m [2];
                $hasDefault = isset ($m [4]);
                // $m[4] is the default string value (without the quote char).
                $defaultExpr = $hasDefault ? "['" . $m [4] . "']" : "[]";
                return $o
                    . "echo implode ('', (\$__drip_stacks ['{$key}'] ?? {$defaultExpr})); "
                    . $c;
            },
            $source
        ) ?? $source;

        return $source;
    }

    /**
     * Compile @once ... @endonce — content rendered only once per render cycle.
     */
    private static function compileOnceDirectives (string $source): string
    {
        $o = "\x3C?php ";
        $c = "?\x3E";

        // ponytail: @once flag is per-execute (per top-level render); nested
        // re-includes of the same template re-render their @once blocks.
        // Add a cross-render static memo if a caller needs global-once.
        $source = preg_replace (
            '/@once\s*\n?/',
            $o . "if (!isset (\$__drip_once_used)) { \$__drip_once_used = true; " . $c,
            $source
        ) ?? $source;

        $source = preg_replace (
            '/@endonce\s*\n?/',
            $o . "}" . $c,
            $source
        ) ?? $source;

        return $source;
    }

    /**
     * Compile component tags: `<x-alert type="info">body</x-alert>` and
     * self-closing `<x-alert type="info" />`.
     *
     * Resolves to `system/Templates/components/alert` (dot-notation for
     * subfolders). Attributes become variables in the component scope;
     * slot content is available as `$slot` (string).
     */
    private static function compileComponentTags (string $source): string
    {
        $o = "\x3C?php ";
        $c = "?\x3E";

        // Paired: <x-name attr="val">slot</x-name>
        $source = preg_replace_callback (
            '/<x-([a-z0-9_]+(?:\.[a-z0-9_]+)*)\s+([^>]*)>(.*?)<\/x-\1>/s',
            function (array $m) use ($o, $c): string
            {
                return self::compileComponentBlock ($m [1], $m [2], $m [3], $o, $c);
            },
            $source
        ) ?? $source;

        // Self-closing: <x-name attr="val" />
        $source = preg_replace_callback (
            '/<x-([a-z0-9_]+(?:\.[a-z0-9_]+)*)\s+([^>]*?)\/>/s',
            function (array $m) use ($o, $c): string
            {
                return self::compileComponentBlock ($m [1], $m [2], '', $o, $c);
            },
            $source
        ) ?? $source;

        return $source;
    }

    /**
     * Build the PHP code for a single component tag.
     *
     * Attributes become entries in $__drip_attrs; slot content becomes
     * the 'slot' entry. The component is rendered via renderShared()
     * with the attrs array extracted into the render scope.
     *
     * @param string $name     Component identifier (dot-notation allowed)
     * @param string $attrs    Raw attribute string from the tag
     * @param string $slot     Slot content (empty for self-closing)
     */
    private static function compileComponentBlock (string $name, string $attrs, string $slot, string $o, string $c): string
    {
        $attrVars = self::parseComponentAttributes ($attrs);
        $slotVar  = self::escapePhpString ($slot);

        $lines = ['$__drip_attrs = [];'];
        foreach ($attrVars as $key => $valueExpr)
        {
            $lines [] = '$__drip_attrs ["' . $key . '"] = ' . $valueExpr . ';';
        }

        $body = implode (' ', $lines);
        if ($slot !== '')
        {
            $body .= ' $__drip_attrs ["slot"] = ' . $slotVar . ';';
        }
        else
        {
            $body .= ' $__drip_attrs ["slot"] ??= "";';
        }

        $idExpr = "'{$name}'";
        return $o . "{$body} echo \System\Services\Drip::renderShared (\System\Services\Drip::resolveShared ("
            . $idExpr . ", 'components'), \$__drip_attrs); "
            . 'unset ($__drip_attrs); '
            . $c;
    }

    /**
     * Parse a component attribute string into an ordered list of
     * key => PHP-expression pairs.
     *
     * Supported: `key="literal"`, `key='literal'`, `key={$expr}`, `key` (bare, true).
     * Literal values are escaped for PHP string context.
     *
     * @return array<string, string> key => PHP expression
     */
    private static function parseComponentAttributes (string $attrs): array
    {
        $result = [];
        $attrs  = trim ($attrs);
        if ($attrs === '') {return $result;}

        // Match key="value" | key='value' | key={$expr} | bare key
        $pattern = '/([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')'
            . '|([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*\{([^}]+)\}'
            . '|([a-zA-Z_][a-zA-Z0-9_-]*)/';

        $offset = 0;
        $len = strlen ($attrs);
        while ($offset < $len)
        {
            $rest = substr ($attrs, $offset);
            // Skip whitespace
            if (preg_match ('/^\s+/', $rest, $ws, PREG_OFFSET_CAPTURE))
            {
                $offset += strlen ($ws [0]);
                continue;
            }

            if (! preg_match ($pattern, $rest, $m, PREG_OFFSET_CAPTURE)) {break;}

            // Quoted literal
            if (isset ($m [1]) && $m [1] !== '')
            {
                $key   = $m [1] [0];
                $value = $m [2] [0];
                // Strip surrounding quotes, unescape.
                $inner = substr ($value, 1, -1);
                $inner = str_replace ('\\\\', '\\', $inner);
                $inner = str_replace ('\\"', '"', $inner);
                $inner = str_replace ("\\'", "'", $inner);
                $result [trim ($key)] = "'" . addcslashes ($inner, "'\\") . "'";
                $offset += strlen ($m [0] [0]);
            }
            // Expression in braces
            elseif (isset ($m [3]) && $m [3] !== '')
            {
                $key  = $m [3] [0];
                $expr = trim ($m [4] [0]);
                $result [trim ($key)] = $expr;
                $offset += strlen ($m [0] [0]);
            }
            // Bare key
            elseif (isset ($m [5]) && $m [5] !== '')
            {
                $result [trim ($m [5] [0])] = 'true';
                $offset += strlen ($m [0] [0]);
            }
            else
            {
                $offset += strlen ($m [0] [0]);
            }
        }

        return $result;
    }

    /**
     * Escape a string for embedding in a PHP single-quoted string literal.
     */
    private static function escapePhpString (string $value): string
    {
        return "'" . str_replace ("'", "\\'", str_replace ('\\', '\\\\', $value)) . "'";
    }

    /**
     * Compile echo tokens: {{ expr }} (escaped) and {!! expr !!} (raw).
     * Emits \System\Services\Drip::e() so the compiled PHP is self-contained.
     * Overridable via Drip::escapedEcho() / Drip::rawEcho().
     */
    private static function compileEchoTokens (string $source): string
    {
        $o  = "\x3C?php ";
        $c  = "?\x3E";

        // Default handlers (overridable via Drip::escapedEcho / Drip::rawEcho).
        $escaped = self::$escapedEcho ?? static fn (string $expr): string
            => '\System\Services\Drip::e (' . $expr . ')';
        $raw     = self::$rawEcho ?? static fn (string $expr): string
            => $expr;

        $source = preg_replace_callback (
            '/\{\{\s*(.*?)\s*\}\}/s',
            function (array $m) use ($escaped, $o, $c): string
            {
                return $o . 'echo ' . $escaped ($m [1]) . '; ' . $c;
            },
            $source
        ) ?? $source;

        $source = preg_replace_callback (
            '/\{!!\s*(.*?)\s*!!\}/s',
            function (array $m) use ($raw, $o, $c): string
            {
                return $o . 'echo ' . $raw ($m [1]) . '; ' . $c;
            },
            $source
        ) ?? $source;

        return $source;
    }

    /**
     * Compile @directive tokens.
     *
     * Uses an if/elseif chain (NOT a switch on $word) to avoid a known
     * PHP scanner bug where reserved words inside switch case labels
     * cause parse errors in certain PHP 8.3 builds.
     */
    private static function compileDirectives (string $source): string
    {
        return preg_replace_callback (
            '/@([a-zA-Z]+)\s*\((.*)\)\n?|@([a-zA-Z]+)(?!\s*\()\n?/',
            function (array $m): string
            {
                if (isset ($m [1]) && $m [1] !== '')
                {
                    $word = strtolower ($m [1]);
                    $args = $m [2] ?? '';
                    return self::compileDirective ($word, $args, $m [0]);
                }
                $word = strtolower ($m [3] ?? '');
                if ($word === '') {return $m [0];}
                return self::compileDirective ($word, '', $m [0]);
            },
            $source
        ) ?? $source;
    }

    /**
     * Compile a single directive word into PHP output.
     * All emitted PHP uses hex-escaped open/close tags to avoid scanner issues.
     */
    private static function compileDirective (string $word, string $args, string $original): string
    {
        $a = trim ($args);
        $o = "\x3C?php ";
        $c = "?\x3E";

        if ($word === 'if')
        {
            return $o . 'if (' . $a . '): ' . $c;
        }
        elseif ($word === 'elseif')
        {
            return $o . 'elseif (' . $a . '): ' . $c;
        }
        elseif ($word === 'else')
        {
            return $o . 'else: ' . $c;
        }
        elseif ($word === 'endif')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'unless')
        {
            return $o . 'if (!(' . $a . ')): ' . $c;
        }
        elseif ($word === 'endunless')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'isset')
        {
            return $o . 'if (isset(' . $a . ')): ' . $c;
        }
        elseif ($word === 'endisset')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'empty')
        {
            if ($a === '')
            {
                return $o . 'endforeach; $__drip_loop_depth = max (0, ($__drip_loop_depth ?? 1) - 1); unset ($loop); if ($__drip_fe_count === 0): ' . $c;
            }
            return $o . 'if (empty(' . $a . ')): ' . $c;
        }
        elseif ($word === 'endempty')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'for')
        {
            return $o . 'for (' . $a . '): ' . $c;
        }
        elseif ($word === 'endfor')
        {
            // ponytail: @for loop metadata ($loop) is not injected in this MVP;
            // @foreach and @forelse are the supported loop-metadata paths.
            return $o . 'endfor; ' . $c;
        }
        elseif ($word === 'foreach')
        {
            return self::compileLoopMetadata ($a, 'foreach', $o, $c);
        }
        elseif ($word === 'endforeach')
        {
            return $o . 'endforeach; $__drip_loop_depth = max (0, ($__drip_loop_depth ?? 1) - 1); unset ($loop); ' . $c;
        }
        elseif ($word === 'forelse')
        {
            return self::compileLoopMetadata ($a, 'forelse', $o, $c);
        }
        elseif ($word === 'endforelse')
        {
            return $o . 'endif; $__drip_loop_depth = max (0, ($__drip_loop_depth ?? 1) - 1); unset ($loop); ' . $c;
        }
        elseif ($word === 'while')
        {
            return $o . 'while (' . $a . '): ' . $c;
        }
        elseif ($word === 'endwhile')
        {
            return $o . 'endwhile; ' . $c;
        }
        elseif ($word === 'switch')
        {
            return $o . 'switch (' . $a . '): ' . $c;
        }
        elseif ($word === 'endswitch')
        {
            return $o . 'endswitch; ' . $c;
        }
        elseif ($word === 'case')
        {
            return $o . 'case ' . $a . ': ' . $c;
        }
        elseif ($word === 'break')
        {
            return $o . 'break; ' . $c;
        }
        elseif ($word === 'continue')
        {
            return $o . 'continue; ' . $c;
        }
        elseif ($word === 'default')
        {
            return $o . 'default: ' . $c;
        }
        elseif ($word === 'include')
        {
            return self::compileInclude ($args);
        }
        elseif ($word === 'verbatim')
        {
            return '';
        }
        elseif ($word === 'endverbatim')
        {
            return '';
        }

        // ── DR3: Loop metadata helpers ──
        elseif ($word === 'loop')
        {
            // @loop → current $__loop object; @loop($var) not supported in MVP.
            return $o . '$__loop = $__loop ?? null;' . $c;
        }

        // ── DR3: Conditional attributes ──
        elseif ($word === 'class')
        {
            return self::compileClass ($a, $o, $c);
        }
        elseif ($word === 'style')
        {
            return self::compileStyle ($a, $o, $c);
        }
        elseif ($word === 'checked')
        {
            // Emit a safe literal: single-quoted ' checked="checked"'.
            $attr = chr (39) . ' checked=' . chr (34) . 'checked' . chr (34) . chr (39);
            return $o . 'if (' . $a . ') {echo ' . $attr . '; }' . $c;
        }
        elseif ($word === 'selected')
        {
            $attr = chr (39) . ' selected=' . chr (34) . 'selected' . chr (34) . chr (39);
            return $o . 'if (' . $a . ') {echo ' . $attr . '; }' . $c;
        }

        // ── DR3: Common PHP directives ──
        elseif ($word === 'csrf')
        {
            return $o . 'if (function_exists (\'wp_nonce_field\')) {wp_nonce_field ();}' . $c;
        }
        elseif ($word === 'auth')
        {
            return $o . 'if (function_exists (\'is_user_logged_in\') && is_user_logged_in ()): ' . $c;
        }
        elseif ($word === 'endauth')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'guest')
        {
            return $o . 'if (!function_exists (\'is_user_logged_in\') || !is_user_logged_in ()): ' . $c;
        }
        elseif ($word === 'endguest')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'can')
        {
            return $o . 'if (function_exists (\'current_user_can\') && current_user_can (' . $a . ')): ' . $c;
        }
        elseif ($word === 'endcan')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'role')
        {
            // @role('editor') — gracefully no-ops outside WP runtime.
            // Uses a named-argument-free closure invoked with the role.
            $roleExpr = trim ($a);
            return $o
                . 'if (function_exists (\'wp_get_current_user\') && '
                . '(function ($__role) {'
                . ' $user = wp_get_current_user ();'
                . ' if (empty ($user) || $user->ID === 0) {return false;}'
                . ' return in_array ($__role, (array) $user->roles, true);'
                . '}) (' . $roleExpr . ')): '
                . $c;
        }
        elseif ($word === 'endrole')
        {
            return $o . 'endif; ' . $c;
        }
        elseif ($word === 'trans')
        {
            return self::compileTrans ($a, $o, $c);
        }
        elseif ($word === 'transChoice')
        {
            return self::compileTransChoice ($a, $o, $c);
        }
        elseif ($word === 'php')
        {
            // Open of a native-PHP block; closed by @endphp.
            return $o;
        }
        elseif ($word === 'endphp')
        {
            return $c;
        }
        elseif ($word === 'dump')
        {
            return $o . 'var_dump (' . $a . ');' . $c;
        }
        elseif ($word === 'dd')
        {
            // Dev-only: var_dump then abort the render cycle via a
            // catchable exception (not exit) so the render returns
            // cleanly to callers instead of killing the process.
            return $o . 'var_dump (' . $a . '); throw new \RuntimeException (\'Drip: @dd aborts the render cycle (dev-only).\');' . $c;
        }
        elseif ($word === 'inject')
        {
            // @inject('var') — pull the named variable from the render-scope
            // data array (stashed in $__drip_data at execute() start).
            // The arg is a quoted string literal; extract the name.
            $key = trim ($a);
            if (str_starts_with ($key, "'") || str_starts_with ($key, '"'))
            {
                $key = substr ($key, 1, -1);
            }
            if ($key !== '')
            {
                return $o
                    . '$__drip_inject_val = $__drip_data [' . chr (39) . $key . chr (39) . '] ?? null;'
                    . ' $' . $key . ' = $__drip_inject_val;'
                    . ' ' . $c;
            }
            return $o . ' ' . $c;
        }
        elseif ($word === 'js')
        {
            return $o . 'echo json_encode (' . $a . ', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ' . $c;
        }
        elseif ($word === 'json')
        {
            return $o . 'echo json_encode (' . $a . '); ' . $c;
        }

        // ── DR3: Custom directives (registered via Drip::directive or
        //    Drip::directivesFromConfig) ──
        // The callable receives the raw args string and returns a PHP
        // statement to splice in place. For bare-expression results
        // (not ending in `;` or `:`) we auto-prepend `echo ` so the
        // handler can return just `strtoupper ($args)` and it still
        // produces valid compiled output.
        elseif (isset (self::$directives [$word]))
        {
            $handler = self::$directives [$word];
            $result  = $handler ($a);
            if ($result === '') {return '';}
            // Auto-echo bare expressions; pass statements through.
            // A result is a "statement" when it ends with `;` or `:`,
            // or contains a keyword that indicates a block/statement.
            $r = rtrim ($result);
            $isStatement = str_ends_with ($r, ';')
                || str_ends_with ($r, ':')
                || $r === '}'
                || str_contains ($result, '{')
                || str_starts_with ($result, 'if (')
                || str_starts_with ($result, 'foreach (')
                || str_starts_with ($result, 'switch (')
                || str_starts_with ($result, 'elseif (');
            if (! $isStatement)
            {
                $result = 'echo ' . $result . ';';
            }
            return $o . $result . $c;
        }

        return $original;
    }

    /**
     * Compile @trans('key') or @trans('key', ['param' => $val]).
     * In WP calls __() / vsprintf() for params; in pure-PHP falls back
     * to the key string itself.
     */
    private static function compileTrans (string $args, string $o, string $c): string
    {
        $parts = self::splitTopLevelComma ($args);
        $key   = trim ($parts [0] ?? '');
        // Strip surrounding quotes if present.
        if (str_starts_with ($key, "'") || str_starts_with ($key, '"'))
        {
            $key = substr ($key, 1, -1);
        }

        $hasParams = count ($parts) > 1;
        $paramsExpr = $hasParams ? trim ($parts [1]) : null;

        $code = '';
        if ($hasParams)
        {
            $code .= '$__trans_val = '
                . "(function_exists ('__') ? __ ('{$key}', 'radykfw') : '{$key}');"
                        . " \$__trans_val = vsprintf (\$__trans_val, array_values (" . $paramsExpr . "));"
                . " echo \\System\\Services\\Drip::e (\$__trans_val); ";
        }
        else
        {
            $code .= '$__trans_val = '
                . "(function_exists ('__') ? __ ('{$key}', 'radykfw') : '{$key}');"
                . " echo \\System\\Services\\Drip::e (\$__trans_val); ";
        }

        return $o . $code . $c;
    }

    /**
     * Compile @transChoice($key, $count) — calls _n() in WP,
     * falls back to the singular key in pure-PHP.
     */
    private static function compileTransChoice (string $args, string $o, string $c): string
    {
        $parts = self::splitTopLevelComma ($args);
        $key   = trim ($parts [0] ?? '');
        $count = trim ($parts [1] ?? '1');
        // Strip surrounding quotes if present.
        if (str_starts_with ($key, "'") || str_starts_with ($key, '"'))
        {
            $key = substr ($key, 1, -1);
        }

        return $o
            . '$__trans_choice_val = (function_exists (\'_n\') '
            . "? _n ('{$key}', '{$key}', " . $count . ", 'radykfw')"
            . " : '{$key}');"
            . " echo \\System\\Services\\Drip::e (\$__trans_choice_val); "
            . $c;
    }

    /**
     * Compile a loop with $loop metadata.
     *
     * Emits a foreach that maintains $__loop (index, iteration, first,
     * last, count, depth, parent) per iteration.
     */
    private static function compileLoopMetadata (string $args, string $loopType, string $o, string $c): string
    {
        $left = trim (explode (' as ', $args) [0]);
        $countExpr = 'is_array (' . $left . ') || ' . $left . ' instanceof \Countable'
                . " ? count (" . $left . ') : 0';

        return $o
                . '$__drip_fe_count = ' . $countExpr . ';'
                . ' $__drip_loop_depth = $__drip_loop_depth ?? 0;'
                . ' $__drip_loop_parent = $loop ?? null;'
                . ' $__drip_loop_depth++;'
                . ' $__drip_loop_i = 0;'
                . ' foreach (' . $args . '):'
                . ' $__drip_loop_i++;'
                . ' $loop = (object) ['
                . '   "index"     => $__drip_loop_i - 1,'
                . '   "iteration" => $__drip_loop_i,'
                . '   "first"     => $__drip_loop_i === 1,'
                . '   "last"      => $__drip_loop_i === $__drip_fe_count,'
                . '   "count"     => $__drip_fe_count,'
                . '   "depth"     => $__drip_loop_depth,'
                . '   "parent"    => $__drip_loop_parent'
                . ' ];'
                . ' '
                . $c;
    }

    /**
     * Compile @class([...]) — evaluates the PHP array at render time.
     * String values are always included; string-key => bool pairs are
     * included when the condition is truthy. The result is emitted as
     * a ` class="..."` attribute (merges with existing via the caller
     * writing it in the right place).
     */
    private static function compileClass (string $args, string $o, string $c): string
    {
        $expr = trim ($args);
        // Build ` class="..."` output with a safe PHP string literal.
        // We emit: echo ' class="' . implode (" ", $list) . '"';
        // using single-quote literals so no backslash-escape issues.
        return $o
            . '$__drip_cls = ' . $expr . ';'
            . ' $__drip_cls = is_array ($__drip_cls) ? '
            . 'array_map (fn ($k, $v) => is_int ($k) ? $v : ($v ? $k : null), '
            . 'array_keys ($__drip_cls), array_values ($__drip_cls)) : [$__drip_cls];'
            . ' $__drip_cls = array_values (array_filter ($__drip_cls, fn ($v) => $v !== null && $v !== ' . chr (39) . chr (39) . '));'
            . ' if ($__drip_cls) {echo ' . chr (39) . ' class=' . chr (34) . chr (39)
            . ' . implode (' . chr (39) . ' ' . chr (39) . ', $__drip_cls)'
            . ' . ' . chr (39) . chr (34) . chr (39) . ';}'
            . ' ' . $c;
    }

    /**
     * Compile @style([...]) — same pattern as @class for CSS declarations.
     */
    private static function compileStyle (string $args, string $o, string $c): string
    {
        $expr = trim ($args);
        return $o
            . '$__drip_sty = ' . $expr . ';'
            . ' $__drip_sty = is_array ($__drip_sty) ? '
            . 'array_map (fn ($k, $v) => is_int ($k) ? $v : ($v ? $k : null), '
            . 'array_keys ($__drip_sty), array_values ($__drip_sty)) : [$__drip_sty];'
            . ' $__drip_sty = array_values (array_filter ($__drip_sty, fn ($v) => $v !== null && $v !== ' . chr (39) . chr (39) . '));'
            . ' $__drip_sty = array_map (fn ($s) => trim ($s), $__drip_sty);'
            . ' $__drip_sty = array_values (array_unique (array_filter (explode (' . chr (39) . '; ' . chr (39) . ', implode (' . chr (39) . '; ' . chr (39) . ', $__drip_sty)))));'
            . ' if ($__drip_sty) {echo ' . chr (39) . ' style=' . chr (34) . chr (39)
            . ' . implode (' . chr (39) . '; ' . chr (39) . ', $__drip_sty)'
            . ' . ' . chr (39) . chr (34) . chr (39) . ';}'
            . ' ' . $c;
    }

    /**
     * Compile an @include directive.
     *
     * Syntax: @include('module:Name.view', ['key' => $value])
     * The identifier MUST be a quoted string literal.
     */
    private static function compileInclude (string $args): string
    {
        $parts = self::splitTopLevelComma ($args);
        if (count ($parts) < 1 || trim ($parts [0]) === '')
        {
            throw new \RuntimeException ("Drip: @include requires an identifier argument");
        }

        $identifier = trim ($parts [0]);

        if (! (str_starts_with ($identifier, "'") && str_ends_with ($identifier, "'"))
            && ! (str_starts_with ($identifier, '"') && str_ends_with ($identifier, '"')))
        {
            throw new \RuntimeException (
                "Drip: @include identifier must be a quoted string literal: '{$identifier}'"
            );
        }

        $dataExpr = count ($parts) > 1 ? trim ($parts [1]) : '[]';
        $o = "\x3C?php ";
        $c = "?\x3E";

        return $o . 'echo \System\Services\Drip::render (' . $identifier . ', ' . $dataExpr . '); ' . $c;
    }

    /**
     * Split a comma-separated argument list at the top level only,
     * respecting nested brackets and quoted strings.
     */
    private static function splitTopLevelComma (string $args): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $len   = strlen ($args);
        $inStr = null;

        for ($i = 0; $i < $len; $i++)
        {
            $c = $args [$i];

            if ($inStr !== null)
            {
                if ($c === $inStr && ($i === 0 || $args [$i - 1] !== '\\')) {$inStr = null;}
                continue;
            }

            if ($c === "'" || $c === '"') {$inStr = $c;}
            elseif ($c === '(' || $c === '[' || $c === '{') {$depth++;}
            elseif ($c === ')' || $c === ']' || $c === '}') {$depth--;}
            elseif ($c === ',' && $depth === 0)
            {
                $parts [] = substr ($args, $start, $i - $start);
                $start    = $i + 1;
            }
        }

        $parts [] = substr ($args, $start);
        return array_map ('trim', $parts);
    }

    // ----------------------------------------------------------------
    // Cache layer (DR5)
    // ----------------------------------------------------------------

    /**
     * Render a resolved template file through the compiler + cache.
     *
     * @param string $file       Absolute path to the source template
     * @param string $identifier Logical identifier (for error messages only)
     * @param array  $data       Extracted into the render scope (EXTR_SKIP)
     */
    private static function execute (string $file, string $identifier, array $data): string
    {
        $source = file_get_contents ($file);
        if ($source === false)
        {
            throw new \RuntimeException ("Drip: template not found: '{$identifier}'");
        }

        $cacheDir  = self::cacheDir ();
        $cacheKey  = md5 (
            self::COMPILER_VERSION . '|'
            . $file . '|'
            . filemtime ($file) . '|'
            . $source
        );
        $cacheFile = $cacheDir . '/' . $cacheKey . '.php';
        $metaFile  = $cacheDir . '/' . $cacheKey . '.meta';

        $cacheValid = false;
        if (is_file ($cacheFile) && is_file ($metaFile))
        {
            $meta = json_decode (file_get_contents ($metaFile) ?: '', true);
            $cacheValid = is_array ($meta)
                && ($meta ['mtime'] ?? null) === filemtime ($file)
                && ($meta ['compiler'] ?? null) === self::COMPILER_VERSION;
        }

        if (!$cacheValid)
        {
            self::writeCachedFiles ($cacheDir, $cacheKey, self::compile ($source), $file);
        }

        ob_start ();
        extract ($data, EXTR_SKIP);

        // Runtime state for DR3 directives.
        $__drip_sections        = $__drip_sections ?? [];
        $__drip_stacks          = $__drip_stacks ?? [];
        $__drip_parent_sections  = $__drip_sections;
        unset ($__drip_once_used);
        $__drip_data             = $data;

        try
        {
            include $cacheFile;
        }
        catch (\Throwable $e)
        {
            ob_end_clean ();
            $clean = self::sanitizeErrorMessage ($e->getMessage (), $identifier);
            throw new \RuntimeException ("Drip: runtime error in '{$identifier}': {$clean}", 0, $e);
        }

        $output = (string) ob_get_clean ();

        // Handle @extends marker: render the layout with the captured
        // sections/stacks and splice in the pre/post content.
        if (str_contains ($output, "\x00DRIPEXT\x00"))
        {
                    $pre      = '';
                    $post     = '';
                    $layoutId = '';
                    $parts    = explode ("\x00DRIPEXT\x00", $output, 3);
                    if (count ($parts) >= 2)
                    {
                        $layoutId = rtrim ($parts [1], "\0");
                        $pre      = $parts [0];
                        $post     = count ($parts) === 3 ? $parts [2] : '';
                    }

                    $layoutFile = \System\Services\Drip::resolveShared ($layoutId, 'layouts');
                    $layoutOut  = self::renderLayout ($layoutFile, $__drip_sections, $__drip_stacks);
                    $output     = $pre . $layoutOut . $post;
                }

                return $output;
    }

    /**
     * Render a layout file with the child's captured sections and stacks.
     * The layout's own @section/@push blocks run in the layout's scope;
     * @parent inside a layout section echoes the parent's section value
     * (inherited from a higher layout, if chained).
     *
     * @param string $file     Absolute path to the layout file
     * @param array  $sections Sections captured by the child (or nested layout)
     * @param array  $stacks   Stacks captured by the child (or nested layout)
     */
    private static function renderLayout (string $file, array $sections, array $stacks): string
    {
        $identifier = 'layout:' . basename ($file);
        return self::execute (
            $file,
            $identifier,
            [
                '__drip_sections'  => $sections,
                '__drip_stacks'    => $stacks,
            ]
        );
    }

    /**
     * Sanitize an error message: replace absolute filesystem paths with
     * the logical identifier so source paths never leak to callers.
     */
    private static function sanitizeErrorMessage (string $message, string $identifier): string
    {
        $clean = preg_replace (
            '#[A-Za-z]:\\\\[^\x00"\']+#',
            "template '{$identifier}'",
            $message
        ) ?? $message;
        $clean = preg_replace (
            '#/[\w.\-/]+\.php(\s*:\s*\d+)?#',
            "template '{$identifier}'" . "\$1",
            $clean
        );
        return $clean;
    }

    /**
     * Writable, non-publicly-accessible cache location: wp_tempdir() when
     * the WordPress runtime is present, else the system temp dir.
     */
    private static function cacheDir (): string
    {
        static $dir = null;
        if ($dir === null)
        {
            $base = function_exists ('wp_tempdir') ? wp_tempdir () : sys_get_temp_dir ();
            $dir  = rtrim ($base, '/\\') . DIRECTORY_SEPARATOR . 'radykfw-drip';
            if (! @is_dir ($dir) && ! @mkdir ($dir, 0777, true))
            {
                $dir = sys_get_temp_dir ();
            }
            $dir = rtrim ($dir, '/\\');
        }
        return $dir;
    }

    /**
     * Write the compiled file + its meta file atomically: write to temp
     * names in the same directory, then rename into place. On any failure
     * (mkdir, write, rename) the existing cached files are left untouched,
     * so a partial entry is never produced.
     */
    private static function writeCachedFiles (string $dir, string $key, string $php, string $file): void
    {
        if (! is_dir ($dir) && ! @mkdir ($dir, 0777, true))
        {
            return;
        }

        $finalPhp  = $dir . '/' . $key . '.php';
        $finalMeta = $dir . '/' . $key . '.meta';
        $suffix    = bin2hex (random_bytes (4));

        if (@file_put_contents ($finalPhp . '.' . $suffix, $php) === false)
        {
            return;
        }
        if (@file_put_contents ($finalMeta . '.' . $suffix, json_encode ([
            'mtime' => filemtime ($file),
            'compiler' => self::COMPILER_VERSION,
        ])) === false)
        {
            @unlink ($finalPhp . '.' . $suffix);
            return;
        }

        @rename ($finalPhp . '.' . $suffix, $finalPhp);
        @rename ($finalMeta . '.' . $suffix, $finalMeta);
    }

    // ----------------------------------------------------------------
    // Resolvers (unchanged from DR1)
    // ----------------------------------------------------------------

    /**
     * Strip the optional kind prefix and '.drip' suffix from an identifier.
     */
    private static function normalize (string $identifier): string
    {
        $id = $identifier;
        foreach (['module:', 'system:', 'error:'] as $prefix)
        {
            if (str_starts_with ($id, $prefix))
            {
                $id = substr ($id, strlen ($prefix));
                break;
            }
        }

        if (str_ends_with ($id, '.drip')) {$id = substr ($id, 0, -strlen ('.drip'));}

        return $id;
    }

    /**
     * Reject unsafe template identifiers: absolute paths, traversal, null bytes.
     * Messages carry the logical identifier only — never a filesystem path.
     *
     * @throws \InvalidArgumentException
     */
    private static function guard (string $identifier, string $kind, string $display = ''): void
    {
        $shown = $display !== '' ? $display : $identifier;

        if ($identifier === '')
        {
            throw new \InvalidArgumentException ("Drip: empty {$kind} identifier.");
        }

        if (str_contains ($identifier, "\0"))
        {
            throw new \InvalidArgumentException ("Drip: null byte in {$kind} identifier: '{$shown}'");
        }

        if (preg_match ('#^(?:[A-Za-z]:|/|\\\\)#', $identifier) === 1)
        {
            throw new \InvalidArgumentException ("Drip: absolute path in {$kind} identifier: '{$shown}'");
        }

        if (str_contains ($identifier, '..'))
        {
            throw new \InvalidArgumentException ("Drip: path traversal in {$kind} identifier: '{$shown}'");
        }

        if (str_contains ($identifier, ':'))
        {
            throw new \InvalidArgumentException ("Drip: invalid character in {$kind} identifier: '{$shown}'");
        }

        foreach (preg_split ('#[./\\\\]#', $identifier) ?: [] as $segment)
        {
            if ($segment === '')
            {
                throw new \InvalidArgumentException ("Drip: empty segment in {$kind} identifier: '{$shown}'");
            }
        }
    }
}
