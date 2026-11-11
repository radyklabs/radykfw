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
 * DR5: Compiled-cache layer + security (path-traversal rejection, clean error messages).
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
    private const COMPILER_VERSION = '1';

    private function __construct () {}

    /**
     * HTML-escape a value for safe interpolation into HTML.
     *
     * Uses htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE, then un-escapes
     * double quotes so only single quotes, ampersands and angle brackets
     * are escaped.
     */
    public static function e (string|int|float|null $value): string
    {
        $result = htmlspecialchars ((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return str_replace ('&quot;', '"', $result);
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
    // Compiler (DR2)
    // ----------------------------------------------------------------

    /**
     * Compile Drip template source into executable PHP.
     *
     * Pipeline (order matters):
     *  1. Mask native-PHP blocks with sentinels.
     *  2. Strip comments ({{-- --}}) — before echo/directive compilation.
     *  3. Compile @verbatim blocks (content becomes raw PHP, protected).
     *  4. Compile echo tokens ({{ }}, {!! !!}) — outside verbatim.
     *  5. Compile @directive tokens via if/elseif chain.
     *  6. Splice native-PHP blocks back.
     *  7. Strip newlines adjacent to PHP tag boundaries.
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

        // 4. Echo tokens (outside verbatim — verbatim content is now in
        //    PHP string context, safe from the echo regex).
        $masked = self::compileEchoTokens ($masked);

        // 5. Directives.
        $masked = self::compileDirectives ($masked);

        // 6. Splice native-PHP blocks back.
        $masked = preg_replace_callback (
            '#\x00PHPBLOCK(\d+)\x00#',
            function (array $m) use ($phpBlocks): string
            {
                $idx = (int) $m [1];
                return isset ($phpBlocks [$idx]) ? $phpBlocks [$idx] : '';
            },
            $masked
        ) ?? $masked;

        // 7. Strip newlines that are adjacent to PHP tag boundaries so that
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
    private static function compileVerbatim (string $source, array &$verbSentinels = []): string
    {
        return preg_replace_callback (
            '#@verbatim\s*(.*?)@endverbatim#s',
            function (array $m) use (&$verbSentinels): string
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
     * Compile echo tokens: {{ expr }} (escaped) and {!! expr !!} (raw).
     * Emits \System\Services\Drip::e() so the compiled PHP is self-contained.
     */
    private static function compileEchoTokens (string $source): string
    {
        $o = "\x3C?php ";
        $c = "?\x3E";
        $fn = '\System\Services\Drip::e';

        $source = preg_replace (
            '/\{\{\s*(.*?)\s*\}\}/s',
            $o . 'echo ' . $fn . "(\$1); " . $c,
            $source
        ) ?? $source;

        $source = preg_replace (
            '/\{!!\s*(.*?)\s*!!\}/s',
            $o . 'echo $1; ' . $c,
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
                return $o . 'endforeach; if ($__drip_fe_count === 0): ' . $c;
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
            return $o . 'endfor; ' . $c;
        }
        elseif ($word === 'foreach')
        {
            return $o . 'foreach (' . $a . '): ' . $c;
        }
        elseif ($word === 'endforeach')
        {
            return $o . 'endforeach; ' . $c;
        }
        elseif ($word === 'forelse')
        {
            $left = trim (explode (' as ', $a) [0]);
            $countExpr = 'is_array (' . $left . ') || ' . $left . ' instanceof \Countable'
                . " ? count (" . $left . ') : 0';
            return $o . '$__drip_fe_count = ' . $countExpr . '; '
                . 'foreach (' . $a . '): ' . $c;
        }
        elseif ($word === 'endforelse')
        {
            return $o . 'endif; ' . $c;
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

        return $original;
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
            self::ensureDir ($cacheDir);
            file_put_contents ($cacheFile, self::compile ($source));
            file_put_contents (
                $metaFile,
                json_encode (['mtime' => filemtime ($file), 'compiler' => self::COMPILER_VERSION])
            );
        }

        ob_start ();
        extract ($data, EXTR_SKIP);

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

        return (string) ob_get_clean ();
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
     * Resolve the cache directory under the system root.
     */
    private static function cacheDir (): string
    {
        static $dir = null;
        if ($dir === null)
        {
            $dir = \system_path ('cache') . '/drip-compiled-' . self::COMPILER_VERSION;
        }
        return $dir;
    }

    /**
     * Ensure a directory exists (recursive).
     */
    private static function ensureDir (string $dir): void
    {
        if (!is_dir ($dir))
        {
            mkdir ($dir, 0755, true);
        }
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
