<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Base controller for authenticated (private) routes.
 *
 * Module controllers that require a logged-in user extend this:
 *
 *     class ProfileController extends \System\Core\PrivateController {}
 *
 * @since 1.0.1
 */
abstract class PrivateController
{
    /**
     * Get the current module name (set by Router before dispatch).
     */
    protected static function module (): string
    {
        return \System\Core\Router::currentModule ();
    }

    /**
     * Enforce authentication. Call at the top of every handler method.
     */
    protected static function requireAuth (): void
    {
        if (!is_user_logged_in ()) {
            wp_safe_redirect (wp_login_url (home_url ('/')));
            exit;
        }
    }
}
