<?php declare (strict_types=1);

/**
 * Shared dispatch fixtures for Router tests.
 *
 * Router tests set Registry `nsmod` to `Tests\Ci\` so alias/dispatch targets
 * like `Cifixture/Cifixture::index` resolve to these classes instead of the
 * real module controllers (no view rendering, no side effects).
 */

namespace Tests\Ci\Cifixture\Controllers;

final class Cifixture
{
    public static function index (): void
    {
        $GLOBALS ['radykfw_test_dispatch'] [] = 'Cifixture::index';
    }
}

final class Panel
{
    public static function index (): void
    {
        $GLOBALS ['radykfw_test_dispatch'] [] = 'Panel::index';
    }

    public static function save (string $a, string $b): void
    {
        $GLOBALS ['radykfw_test_dispatch'] [] = 'Panel::save:' . $a . ',' . $b;
    }
}

namespace Tests\Ci\Welcome\Controllers;

final class Welcome
{
    public static function index (): void
    {
        $GLOBALS ['radykfw_test_dispatch'] [] = 'Welcome::index';
    }
}
