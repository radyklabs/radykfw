<?php declare (strict_types=1);

namespace RadykFW\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use System\Services\Settings;

final class SettingsServiceTest extends TestCase
{
    public function testValidatesLocaleAndAllowsClearingAnOverride (): void
    {
        $this->assertSame ('id_ID', Settings::validateConfigValue ('admin_locale', 'id_ID'));
        $this->assertNull (Settings::validateConfigValue ('admin_locale', ''));
    }

    public function testRejectsInvalidLocaleValues (): void
    {
        $this->expectException (InvalidArgumentException::class);
        Settings::validateConfigValue ('admin_locale', '../id_ID');
    }

    public function testBooleanSettingsAcceptOnlyBooleanRepresentations (): void
    {
        $this->assertTrue (Settings::validateConfigValue ('routing.native', '1'));
        $this->assertFalse (Settings::validateConfigValue ('routing.native', 0));
    }

    public function testRejectsNonBooleanValues (): void
    {
        $this->expectException (InvalidArgumentException::class);
        Settings::validateConfigValue ('routing.native', 'yes');
    }

    public function testRejectsUnregisteredSettings (): void
    {
        $this->expectException (InvalidArgumentException::class);
        Settings::validateConfigValue ('unknown.setting', 'value');
    }
}
