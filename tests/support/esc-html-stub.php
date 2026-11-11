<?php declare (strict_types=1);

/**
 * Lazy global stub for esc_html().
 *
 * Defined only when AdminUIMenuTest actually runs, not during PHPUnit's
 * file-discovery phase. This keeps DripCompilerTest's assertion
 * assertFalse(function_exists('esc_html')) valid in a pure-PHP context.
 */

if (! function_exists ('esc_html'))
{
    function esc_html ($text): string
    {
        return htmlspecialchars ((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
