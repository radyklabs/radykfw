<?php declare (strict_types=1);

namespace System\Services;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Actions — Nonce/CSRF protection service.
 *
 * Static registry for nonce actions. Used by forms and handlers
 * to register and verify nonce actions.
 */
final class Actions
{
    /** @var array<string, string> Nonce actions registered. */
    private static array $nonce_actions = [];

    private function __construct () {}

    /**
     * Register a nonce action for CSRF protection.
     */
    public static function registerNonceAction (string $action): void
    {
        self::$nonce_actions [] = $action;
    }

    /**
     * Verify a nonce for a registered action.
     */
    public static function verifyNonce (string $action, string $nonce_field = '_wpnonce'): bool
    {
        if (! in_array ($action, self::$nonce_actions, true)) {return false;}
        return wp_verify_nonce ($_POST [$nonce_field] ?? '', $action);
    }

    /**
     * Get all registered nonce actions (read-only).
     */
    public static function getNonceActions (): array
    {
        return self::$nonce_actions;
    }
}
