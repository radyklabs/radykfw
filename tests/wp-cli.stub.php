<?php declare (strict_types=1);

/** Static-analysis signatures only; never loaded at runtime. */
class WP_CLI
{
    public static function add_command (string $name, callable $callback, array $options = []): void {}
    public static function line (string $message): void {}
    public static function halt (int $code): void {}
    public static function error (string $message): void {}
}
