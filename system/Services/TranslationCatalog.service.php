<?php declare (strict_types=1);

namespace System\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

final class TranslationCatalog
{
    private const TEXT_DOMAIN_ARGUMENTS = [
        '__'             => ['domain' => 1],
        '_e'             => ['domain' => 1],
        '_x'             => ['context' => 1, 'domain' => 2],
        '_ex'            => ['context' => 1, 'domain' => 2],
        '_n'             => ['plural' => 1, 'domain' => 3],
        '_nx'            => ['plural' => 1, 'context' => 3, 'domain' => 4],
        '_n_noop'        => ['plural' => 1, 'domain' => 2],
        '_nx_noop'       => ['plural' => 1, 'context' => 2, 'domain' => 3],
        'esc_html__'     => ['domain' => 1],
        'esc_html_e'     => ['domain' => 1],
        'esc_html_x'     => ['context' => 1, 'domain' => 2],
        'esc_attr__'     => ['domain' => 1],
        'esc_attr_e'     => ['domain' => 1],
        'esc_attr_x'     => ['context' => 1, 'domain' => 2],
    ];

    private const MAX_CATALOG_BYTES = 16777216;
    private const MAX_CATALOG_ENTRIES = 100000;

    private function __construct () {}

    public static function generatePot (string $projectRoot, array $sourcePaths, string $domain): string
    {
        if ($domain === '') {throw new RuntimeException ('A text domain is required.');}

        $root = realpath ($projectRoot);
        if ($root === false || ! is_dir ($root))
        {
            throw new RuntimeException ("Project source root is not a readable directory: {$projectRoot}");
        }

        $entries = [];
        $files = self::sourceFiles ($root, $sourcePaths);
        foreach ($files as $file)
        {
            $source = self::readFile ($file);
            $tokens = token_get_all ($source);
            foreach ($tokens as $index => $token)
            {
                if (! is_array ($token)
                    || ! in_array ($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true))
                {
                    continue;
                }

                $name = strtolower (ltrim ($token[1], '\\'));
                if (str_contains ($name, '\\')) {continue;}
                if (! isset (self::TEXT_DOMAIN_ARGUMENTS[$name])) {continue;}

                $open = self::nextSignificantToken ($tokens, $index + 1);
                if ($open === null || self::tokenText ($tokens[$open]) !== '(') {continue;}

                $arguments = self::callArguments ($tokens, $open);
                if ($arguments === null) {continue;}

                $signature = self::TEXT_DOMAIN_ARGUMENTS[$name];
                $msgid = self::literalArgument ($arguments, 0);
                $msgdomain = self::literalArgument ($arguments, $signature['domain']);
                if ($msgid === null || $msgdomain !== $domain) {continue;}

                $context = isset ($signature['context'])
                    ? self::literalArgument ($arguments, $signature['context'])
                    : null;
                $plural = isset ($signature['plural'])
                    ? self::literalArgument ($arguments, $signature['plural'])
                    : null;

                if (isset ($signature['context']) && $context === null) {continue;}
                if (isset ($signature['plural']) && $plural === null) {continue;}

                $key = self::entryKey ($context, $msgid);
                if (! isset ($entries[$key]))
                {
                    $entries[$key] = [
                        'context' => $context,
                        'msgid' => $msgid,
                        'plural' => $plural,
                        'references' => [],
                    ];
                }
                elseif ($entries[$key]['plural'] !== $plural)
                {
                    throw new RuntimeException ("Conflicting plural forms for translation string in {$file}.");
                }

                $line = $token[2];
                $relative = self::relativePath ($root, $file);
                $entries[$key]['references'][$relative . ':' . $line] = true;
            }
        }

        uasort (
            $entries,
            static fn (array $left, array $right): int =>
                [$left['context'] ?? '', $left['msgid']] <=> [$right['context'] ?? '', $right['msgid']]
        );

        $pot = self::potHeader ($domain);
        foreach ($entries as $entry)
        {
            $references = array_keys ($entry['references']);
            sort ($references, SORT_STRING);
            $pot .= "\n";
            if ($references !== []) {$pot .= '#: ' . implode (' ', $references) . "\n";}
            $pot .= self::formatPoEntry ($entry, true);
        }

        return $pot;
    }

    public static function writePot (string $projectRoot, array $sourcePaths, string $domain, string $potPath): void
    {
        self::assertExtension ($potPath, 'pot');
        self::writeFile ($potPath, self::generatePot ($projectRoot, $sourcePaths, $domain));
    }

    public static function compilePo (string $poPath, string $moPath): void
    {
        self::assertExtension ($poPath, 'po');
        self::assertExtension ($moPath, 'mo');

        $entries = self::parsePo (self::readFile ($poPath));
        $compiled = [];
        foreach ($entries as $entry)
        {
            if ($entry['fuzzy']) {continue;}

            $original = $entry['msgid'];
            if ($entry['context'] !== null) {$original = $entry['context'] . "\x04" . $original;}
            if ($entry['plural'] !== null) {$original .= "\0" . $entry['plural'];}

            $translation = $entry['translations'];
            if ($entry['plural'] !== null)
            {
                ksort ($translation, SORT_NUMERIC);
                $maxIndex = $translation === [] ? 0 : max (array_keys ($translation));
                $pluralTranslations = [];
                for ($index = 0; $index <= $maxIndex; ++$index)
                {
                    $pluralTranslations[] = $translation[$index] ?? '';
                }
                $translationValue = implode ("\0", $pluralTranslations);
            }
            else
            {
                $translationValue = $translation[0] ?? '';
            }

            if (isset ($compiled[$original]))
            {
                throw new RuntimeException ('PO catalog contains a duplicate message identifier.');
            }
            $compiled[$original] = $translationValue;
        }

        ksort ($compiled, SORT_STRING);
        self::writeFile ($moPath, self::buildMo ($compiled));
    }

    public static function decompileMo (string $moPath, string $poPath): void
    {
        self::assertExtension ($moPath, 'mo');
        self::assertExtension ($poPath, 'po');

        $messages = self::parseMo (self::readFile ($moPath));
        $po = '';
        foreach ($messages as $original => $translation)
        {
            $context = null;
            $plural = null;
            $msgid = $original;

            if (str_contains ($msgid, "\x04"))
            {
                [$context, $msgid] = explode ("\x04", $msgid, 2);
            }
            if (str_contains ($msgid, "\0"))
            {
                [$msgid, $plural] = explode ("\0", $msgid, 2);
            }

            if ($po !== '') {$po .= "\n";}
            $translationForms = explode ("\0", $translation);
            $po .= self::formatPoEntry ([
                'context' => $context,
                'msgid' => $msgid,
                'plural' => $plural,
                'translations' => $plural === null
                    ? [0 => $translation]
                    : array_combine (array_keys ($translationForms), $translationForms),
            ], false);
        }

        self::writeFile ($poPath, $po);
    }

    private static function sourceFiles (string $root, array $sourcePaths): array
    {
        $files = [];
        $rootPrefix = rtrim ($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach ($sourcePaths as $sourcePath)
        {
            if (! is_string ($sourcePath) || $sourcePath === '')
            {
                throw new RuntimeException ('Source paths must be non-empty strings.');
            }

            $path = str_starts_with ($sourcePath, $rootPrefix)
                ? $sourcePath
                : $rootPrefix . ltrim ($sourcePath, DIRECTORY_SEPARATOR);
            $resolved = realpath ($path);
            if ($resolved === false || ($resolved !== $root && ! str_starts_with ($resolved, $rootPrefix)))
            {
                throw new RuntimeException ("Source path is missing or outside the project root: {$sourcePath}");
            }

            if (is_file ($resolved))
            {
                self::addSourceFile ($files, $resolved);
                continue;
            }

            if (! is_dir ($resolved))
            {
                throw new RuntimeException ("Source path is not a file or directory: {$sourcePath}");
            }

            $iterator = new RecursiveIteratorIterator (
                new RecursiveDirectoryIterator ($resolved, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item)
            {
                if ($item->isFile ())
                {
                    $file = realpath ($item->getPathname ());
                    if ($file !== false && str_starts_with ($file, $rootPrefix))
                    {
                        self::addSourceFile ($files, $file);
                    }
                }
            }
        }

        $files = array_keys ($files);
        sort ($files, SORT_STRING);
        return $files;
    }

    private static function addSourceFile (array &$files, string $file): void
    {
        if (in_array (strtolower (pathinfo ($file, PATHINFO_EXTENSION)), ['php', 'html'], true))
        {
            $files[$file] = true;
        }
    }

    private static function nextSignificantToken (array $tokens, int $index): ?int
    {
        for ($count = count ($tokens); $index < $count; ++$index)
        {
            if (is_array ($tokens[$index])
                && in_array ($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
            {
                continue;
            }
            return $index;
        }
        return null;
    }

    private static function callArguments (array $tokens, int $openIndex): ?array
    {
        $arguments = [];
        $current = [];
        $parentheses = 0;
        $brackets = 0;
        $braces = 0;

        for ($index = $openIndex + 1, $count = count ($tokens); $index < $count; ++$index)
        {
            $text = self::tokenText ($tokens[$index]);
            if ($text === '(') {++$parentheses;}
            elseif ($text === ')')
            {
                if ($parentheses === 0 && $brackets === 0 && $braces === 0)
                {
                    if ($current !== [] || $arguments !== []) {$arguments[] = $current;}
                    return $arguments;
                }
                --$parentheses;
            }
            elseif ($text === '[') {++$brackets;}
            elseif ($text === ']') {--$brackets;}
            elseif ($text === '{') {++$braces;}
            elseif ($text === '}') {--$braces;}

            if ($text === ',' && $parentheses === 0 && $brackets === 0 && $braces === 0)
            {
                $arguments[] = $current;
                $current = [];
                continue;
            }

            $current[] = $tokens[$index];
        }

        return null;
    }

    private static function literalArgument (array $arguments, int $position): ?string
    {
        if (! isset ($arguments[$position])) {return null;}

        $tokens = array_values (array_filter (
            $arguments[$position],
            static fn (mixed $token): bool =>
                ! is_array ($token)
                || ! in_array ($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        if (count ($tokens) !== 1
            || ! is_array ($tokens[0])
            || $tokens[0][0] !== T_CONSTANT_ENCAPSED_STRING)
        {
            return null;
        }

        $literal = $tokens[0][1];
        $quote = $literal[0];
        $body = substr ($literal, 1, -1);
        if ($quote === '"')
        {
            return stripcslashes ($body);
        }

        return preg_replace_callback (
            '/\\\\([\\\\\'])/',
            static fn (array $match): string => $match[1],
            $body
        );
    }

    private static function tokenText (mixed $token): string
    {
        return is_array ($token) ? $token[1] : $token;
    }

    private static function entryKey (?string $context, string $msgid): string
    {
        return ($context ?? '') . "\x04" . $msgid;
    }

    private static function potHeader (string $domain): string
    {
        $created = gmdate ('Y-m-d H:i+0000');
        return 'msgid ""' . "\n"
            . 'msgstr ""' . "\n"
            . self::poQuote ("Project-Id-Version: {$domain}\n") . "\n"
            . self::poQuote ("POT-Creation-Date: {$created}\n") . "\n"
            . self::poQuote ("MIME-Version: 1.0\n") . "\n"
            . self::poQuote ("Content-Type: text/plain; charset=UTF-8\n") . "\n"
            . self::poQuote ("Content-Transfer-Encoding: 8bit\n") . "\n";
    }

    private static function formatPoEntry (array $entry, bool $template): string
    {
        $output = '';
        if (isset ($entry['context']) && $entry['context'] !== null)
        {
            $output .= 'msgctxt ' . self::poQuote ($entry['context']) . "\n";
        }

        $output .= 'msgid ' . self::poQuote ($entry['msgid']) . "\n";
        if (isset ($entry['plural']) && $entry['plural'] !== null)
        {
            $output .= 'msgid_plural ' . self::poQuote ($entry['plural']) . "\n";
            if ($template)
            {
                $output .= 'msgstr[0] ""' . "\n" . 'msgstr[1] ""' . "\n";
            }
            else
            {
                foreach ($entry['translations'] as $index => $translation)
                {
                    $output .= 'msgstr[' . $index . '] ' . self::poQuote ($translation) . "\n";
                }
            }
            return $output;
        }

        $translation = $template
            ? ''
            : ($entry['translations'][0] ?? '');
        return $output . 'msgstr ' . self::poQuote ($translation) . "\n";
    }

    private static function parsePo (string $source): array
    {
        $entries = [];
        $entry = null;
        $activeField = null;
        $pendingFuzzy = false;

        $finishEntry = static function () use (&$entry, &$entries, &$activeField, &$pendingFuzzy): void {
            if (is_array ($entry))
            {
                if (! array_key_exists ('msgid', $entry))
                {
                    throw new RuntimeException ('PO catalog contains an entry without msgid.');
                }
                $key = self::entryKey ($entry['context'], $entry['msgid']);
                if (isset ($entries[$key]))
                {
                    throw new RuntimeException ('PO catalog contains a duplicate message identifier.');
                }
                $entries[$key] = $entry;
            }
            $entry = null;
            $activeField = null;
            $pendingFuzzy = false;
        };

        foreach (preg_split ('/\r\n|\n|\r/', $source) as $line)
        {
            if (trim ($line) === '')
            {
                $finishEntry ();
                continue;
            }
            if (str_starts_with ($line, '#~'))
            {
                continue;
            }
            if (str_starts_with ($line, '#'))
            {
                if (preg_match ('/^#,\s*.*\bfuzzy\b/', $line))
                {
                    if ($entry !== null) {$entry['fuzzy'] = true;}
                    else {$pendingFuzzy = true;}
                }
                continue;
            }

            if (preg_match ('/^(msgctxt|msgid|msgid_plural|msgstr)(?:\[(\d+)\])?\s+("(?:\\\\.|[^"\\\\])*")$/', $line, $match))
            {
                $field = $match[1];
                $pluralIndex = isset ($match[2]) && $match[2] !== '' ? (int) $match[2] : null;

                if ($field === 'msgid' && $entry !== null && $entry['msgid'] !== null)
                {
                    $finishEntry ();
                }
                if ($entry === null)
                {
                    $entry = [
                        'context' => null,
                        'msgid' => null,
                        'plural' => null,
                        'translations' => [],
                        'fuzzy' => $pendingFuzzy,
                    ];
                    $pendingFuzzy = false;
                }

                $value = self::decodePoString ($match[3]);
                if ($field === 'msgstr')
                {
                    $entry['translations'][$pluralIndex ?? 0] = $value;
                    $activeField = 'translations:' . ($pluralIndex ?? 0);
                }
                else
                {
                    $property = match ($field) {
                        'msgctxt' => 'context',
                        'msgid' => 'msgid',
                        'msgid_plural' => 'plural',
                    };
                    $entry[$property] = $value;
                    $activeField = $property;
                }
                continue;
            }

            if (preg_match ('/^\s*("(?:\\\\.|[^"\\\\])*")$/', $line, $match)
                && $entry !== null && $activeField !== null)
            {
                $value = self::decodePoString ($match[1]);
                if (str_starts_with ($activeField, 'translations:'))
                {
                    $index = (int) substr ($activeField, strlen ('translations:'));
                    $entry['translations'][$index] .= $value;
                }
                else
                {
                    $entry[$activeField] .= $value;
                }
                continue;
            }

            throw new RuntimeException ('PO catalog contains an unsupported or malformed line.');
        }
        $finishEntry ();

        return array_values ($entries);
    }

    private static function decodePoString (string $literal): string
    {
        return stripcslashes (substr ($literal, 1, -1));
    }

    private static function parseMo (string $source): array
    {
        if (strlen ($source) < 28) {throw new RuntimeException ('MO catalog header is incomplete.');}

        $littleMagic = unpack ('Vvalue', substr ($source, 0, 4))['value'];
        if ($littleMagic === 0x950412de) {$format = 'V';}
        elseif (unpack ('Nvalue', substr ($source, 0, 4))['value'] === 0x950412de) {$format = 'N';}
        else {throw new RuntimeException ('MO catalog has an invalid magic number.');}

        $header = unpack ($format . 'magic/' . $format . 'revision/' . $format . 'count/'
            . $format . 'originals/' . $format . 'translations/' . $format . 'hashSize/'
            . $format . 'hashOffset', substr ($source, 0, 28));
        if ($header['revision'] !== 0) {throw new RuntimeException ('MO catalog revision is not supported.');}
        if ($header['count'] > self::MAX_CATALOG_ENTRIES)
        {
            throw new RuntimeException ('MO catalog contains too many entries.');
        }

        $originals = self::readMoTable ($source, $header['originals'], $header['count'], $format);
        $translations = self::readMoTable ($source, $header['translations'], $header['count'], $format);
        if (count ($originals) !== count ($translations))
        {
            throw new RuntimeException ('MO catalog tables have different entry counts.');
        }

        $messages = [];
        foreach ($originals as $index => $original)
        {
            if (isset ($messages[$original]))
            {
                throw new RuntimeException ('MO catalog contains a duplicate message identifier.');
            }
            $messages[$original] = $translations[$index];
        }

        return $messages;
    }

    private static function readMoTable (string $source, int $tableOffset, int $count, string $format): array
    {
        $sourceLength = strlen ($source);
        if ($tableOffset > $sourceLength || $count > intdiv ($sourceLength - $tableOffset, 8))
        {
            throw new RuntimeException ('MO catalog string table is outside the file bounds.');
        }

        $strings = [];
        for ($index = 0; $index < $count; ++$index)
        {
            $pair = unpack (
                $format . 'length/' . $format . 'offset',
                substr ($source, $tableOffset + ($index * 8), 8)
            );
            $end = $pair['offset'] + $pair['length'];
            if ($end >= $sourceLength || $source[$end] !== "\0")
            {
                throw new RuntimeException ('MO catalog string data is outside the file bounds.');
            }
            $strings[] = substr ($source, $pair['offset'], $pair['length']);
        }

        return $strings;
    }

    private static function buildMo (array $messages): string
    {
        if (count ($messages) > self::MAX_CATALOG_ENTRIES)
        {
            throw new RuntimeException ('PO catalog contains too many entries.');
        }

        $count = count ($messages);
        $originalTableOffset = 28;
        $translationTableOffset = $originalTableOffset + ($count * 8);
        $stringOffset = $translationTableOffset + ($count * 8);
        $originalTable = '';
        $translationTable = '';
        $originalData = '';
        $translationData = '';

        foreach ($messages as $original => $translation)
        {
            $originalTable .= pack ('V2', strlen ($original), $stringOffset + strlen ($originalData));
            $originalData .= $original . "\0";
        }
        foreach ($messages as $translation)
        {
            $translationTable .= pack ('V2', strlen ($translation), $stringOffset + strlen ($originalData) + strlen ($translationData));
            $translationData .= $translation . "\0";
        }

        $header = pack (
            'V7',
            0x950412de,
            0,
            $count,
            $originalTableOffset,
            $translationTableOffset,
            0,
            0
        );

        return $header . $originalTable . $translationTable . $originalData . $translationData;
    }

    private static function readFile (string $path): string
    {
        if (! is_file ($path) || ! is_readable ($path))
        {
            throw new RuntimeException ("Catalog file is not readable: {$path}");
        }
        $size = filesize ($path);
        if ($size === false || $size > self::MAX_CATALOG_BYTES)
        {
            throw new RuntimeException ("Catalog exceeds the " . self::MAX_CATALOG_BYTES . ' byte size limit.');
        }
        $contents = file_get_contents ($path);
        if ($contents === false) {throw new RuntimeException ("Could not read catalog file: {$path}");}
        return $contents;
    }

    private static function writeFile (string $path, string $contents): void
    {
        $directory = dirname ($path);
        if (! is_dir ($directory) || ! is_writable ($directory))
        {
            throw new RuntimeException ("Catalog output directory is not writable: {$directory}");
        }
        if (strlen ($contents) > self::MAX_CATALOG_BYTES)
        {
            throw new RuntimeException ('Generated catalog exceeds the size limit.');
        }
        if (file_put_contents ($path, $contents, LOCK_EX) === false)
        {
            throw new RuntimeException ("Could not write catalog file: {$path}");
        }
    }

    private static function assertExtension (string $path, string $expected): void
    {
        if (strtolower (pathinfo ($path, PATHINFO_EXTENSION)) !== $expected)
        {
            throw new RuntimeException ("Expected a .{$expected} catalog file: {$path}");
        }
    }

    private static function relativePath (string $root, string $path): string
    {
        return str_replace (DIRECTORY_SEPARATOR, '/', substr ($path, strlen (rtrim ($root, DIRECTORY_SEPARATOR)) + 1));
    }

    private static function poQuote (string $value): string
    {
        $escaped = str_replace (
            ["\\", '"', "\t", "\r", "\n"],
            ["\\\\", '\\"', '\\t', '\\r', '\\n'],
            $value
        );
        $escaped = preg_replace_callback (
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
            static fn (array $match): string => sprintf ('\\%03o', ord ($match[0])),
            $escaped
        );
        return '"' . $escaped . '"';
    }
}
