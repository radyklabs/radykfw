<?php declare (strict_types=1);

/**
 * Standalone check for Runner::declaredType() — no PHPUnit required:
 *
 *   php tests/HelperTypeScanCheck.php
 *
 * Guards the helper-file type scan: declarations are detected, while
 * `Foo::class` constants, strings and docblocks are not false-positives.
 */

require __DIR__ . '/bootstrap.php';

use System\Core\Runner;

$method = new ReflectionMethod (Runner::class, 'declaredType');

$cases = [
    'plain functions'   => ["<?php\nfunction a () {}\nfunction b () {}\n", null],
    'class'             => ["<?php\nclass Foo {}\n", 'class'],
    'anonymous class'   => ["<?php\n\$o = new class {};\n", 'class'],
    '::class constant'  => ["<?php\n\$x = Foo::class;\n", null],
    'interface'         => ["<?php\ninterface I {}\n", 'interface'],
    'trait'             => ["<?php\ntrait T {}\n", 'trait'],
    'enum'              => ["<?php\nenum E { case A; }\n", 'enum'],
    'class in string'   => ["<?php\nfunction a () { return 'class Foo'; }\n", null],
    'class in docblock' => ["<?php\n/** class Foo */\nfunction a () {}\n", null],
];

$failed  = 0;
$tempdir = sys_get_temp_dir ();

foreach ($cases as $label => [$source, $expected])
{
    $file = tempnam ($tempdir, 'radyk');
    file_put_contents ($file, $source);
    $actual = $method->invoke (null, $file);
    unlink ($file);

    if ($actual !== $expected)
    {
        fwrite (STDERR, "FAIL {$label}: expected " . var_export ($expected, true)
            . ', got ' . var_export ($actual, true) . PHP_EOL);
        $failed++;
    }
    else {echo "ok   {$label}" . PHP_EOL;}
}

if ($failed > 0) {fwrite (STDERR, "{$failed} case(s) failed" . PHP_EOL); exit (1);}

echo 'All ' . count ($cases) . ' cases passed' . PHP_EOL;
