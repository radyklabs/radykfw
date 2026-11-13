<?php declare (strict_types=1);

/**
 * Drip custom directives config.
 *
 * Optional file. When present, load it with:
 *
 *   Drip::directivesFromConfig (get_config ('directives', []));
 *
 * Schema per entry:
 *   'name' => [
 *       'handler' => callable|string,  // closure, global function name,
 *                                      // or 'Class::method' string
 *       'close'   => '@endname',       // optional; present = open/close pair,
 *                                      // absent = single-line directive
 *   ]
 *
 * - `handler` receives the raw argument string (inside the parens of
 *   `@name(args)`) and returns a PHP statement to splice into the
 *   compiled template. For a single-line echo directive return
 *   `'echo <expr>;'`; for an open/close pair return the open statement
 *   (e.g. `'if (<expr>):'`), the close is always `endif;`.
 * - `close` is the close-directive name (with or without `@`); omit it
 *   to use the default `@end{name}`.
 * - Names that collide with built-in directives are silently ignored;
 *   built-ins always take precedence and are never overridden.
 * - Invalid names (spaces, `@`, `.`, `/`, `\`, etc.) are skipped.
 */
return [
    // Example single-line directive:
    // 'greet' => ['handler' => fn (string $args) => 'echo "Hello";'],
];
