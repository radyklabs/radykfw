<?php declare (strict_types=1);

namespace RadykFW\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use System\Services\I18n;
use System\Services\TranslationCatalog;

final class I18nServiceTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp (): void
    {
        $this->tempDirectory = sys_get_temp_dir () . sep
            . 'radykfw-i18n-' . bin2hex (random_bytes (8));
        if (! mkdir ($this->tempDirectory, 0700) && ! is_dir ($this->tempDirectory))
        {
            throw new RuntimeException ('Could not create the temporary i18n test directory.');
        }
    }

    protected function tearDown (): void
    {
        if (! is_dir ($this->tempDirectory)) {return;}

        $iterator = new \RecursiveIteratorIterator (
            new \RecursiveDirectoryIterator ($this->tempDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item)
        {
            if ($item->isDir ()) {rmdir ($item->getPathname ());}
            else {unlink ($item->getPathname ());}
        }
        rmdir ($this->tempDirectory);
    }

    public function testLocaleUsesWordPressDefaultUnlessAdminOverrideIsConfigured (): void
    {
        $this->assertSame (
            'fr_FR',
            I18n::resolveLocale ('fr_FR', 'radykfw', true, null, null)
        );
        $this->assertSame (
            'id_ID',
            I18n::resolveLocale ('en_US', 'radykfw', true, 'id_ID', null)
        );
        $this->assertSame (
            'id_ID',
            I18n::resolveLocale ('en_US', 'radykfw', true, null, 'id_ID')
        );
        $this->assertSame (
            'fr_FR',
            I18n::resolveLocale ('fr_FR', 'radykfw', false, 'id_ID', 'id_ID')
        );
        $this->assertSame (
            'fr_FR',
            I18n::resolveLocale ('fr_FR', 'another-domain', true, 'id_ID', 'id_ID')
        );
    }

    public function testInvalidLocaleOverrideRaisesWarningAndPreservesWordPressLocale (): void
    {
        // expectWarning() was removed in PHPUnit 10; capture the warning manually.
        $warnings = [];
        set_error_handler (static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            $resolved = I18n::resolveLocale ('en_US', 'radykfw', true, '../id_ID', null);
        } finally {
            restore_error_handler ();
        }

        $this->assertNotEmpty ($warnings, 'Invalid locale override must raise a warning');
        $this->assertSame ('en_US', $resolved);
    }

    public function testPotExtractionIncludesLiteralStringsFromTheConfiguredDomain (): void
    {
        $sourceDirectory = $this->tempDirectory . sep . 'src';
        mkdir ($sourceDirectory);
        file_put_contents (
            $sourceDirectory . sep . 'sample.php',
            <<<'PHP'
<?php
esc_html__('Welcome', 'radykfw');
_x('Open', 'verb', 'radykfw');
_n('file', 'files', $count, 'radykfw');
__('Ignored', 'another-domain');
__('Dynamic ' . $name, 'radykfw');
PHP
        );

        $pot = TranslationCatalog::generatePot ($this->tempDirectory, ['src'], 'radykfw');

        $this->assertStringContainsString ('msgid "Welcome"', $pot);
        $this->assertStringContainsString ("msgctxt \"verb\"\nmsgid \"Open\"", $pot);
        $this->assertStringContainsString ("msgid \"file\"\nmsgid_plural \"files\"", $pot);
        $this->assertStringNotContainsString ('msgid "Ignored"', $pot);
        $this->assertStringNotContainsString ('Dynamic ', $pot);
        $this->assertStringContainsString ('#: src/sample.php:', $pot);
    }

    public function testPoCompilesToMoAndCanBeDecompiled (): void
    {
        $poPath = $this->tempDirectory . sep . 'radykfw-id_ID.po';
        $moPath = $this->tempDirectory . sep . 'radykfw-id_ID.mo';
        $roundTripPath = $this->tempDirectory . sep . 'round-trip.po';
        file_put_contents (
            $poPath,
            <<<'PO'
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"

msgctxt "verb"
msgid "Open"
msgstr "Buka"

msgid "file"
msgid_plural "files"
msgstr[0] "berkas"
msgstr[1] "berkas-berkas"

#, fuzzy
msgid "Draft"
msgstr "Draf"

#~ msgid "Obsolete"
#~ msgstr "Usang"
PO
        );

        TranslationCatalog::compilePo ($poPath, $moPath);
        TranslationCatalog::decompileMo ($moPath, $roundTripPath);
        $roundTrip = file_get_contents ($roundTripPath);

        $this->assertSame (0x950412de, unpack ('Vvalue', file_get_contents ($moPath, false, null, 0, 4))['value']);
        $this->assertStringContainsString ('msgctxt "verb"', $roundTrip);
        $this->assertStringContainsString ('msgstr "Buka"', $roundTrip);
        $this->assertStringContainsString ('msgid_plural "files"', $roundTrip);
        $this->assertStringContainsString ('msgstr[1] "berkas-berkas"', $roundTrip);
        $this->assertStringNotContainsString ('msgid "Draft"', $roundTrip);
        $this->assertStringNotContainsString ('msgid "Obsolete"', $roundTrip);
    }

    public function testCatalogExtensionsAreValidated (): void
    {
        $this->expectException (RuntimeException::class);
        TranslationCatalog::compilePo (
            $this->tempDirectory . sep . 'input.txt',
            $this->tempDirectory . sep . 'output.mo'
        );
    }
}
