<?php declare (strict_types=1);

namespace System\Middleware;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

use System\Contracts\MiddlewareInterface;

/**
 * Built-in auth middleware: redirect anonymous users to the login URL.
 *
 * Registered explicitly in system/Config/middleware.conf.php ('auth').
 * No auto-discovery; implementations live in system/Middleware/.
 */
final class Auth implements MiddlewareInterface
{
    public function allow (array $route): bool
    {
        if (is_user_logged_in ()) {return true;}
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url ($uri, PHP_URL_PATH);
        $query = parse_url ($uri, PHP_URL_QUERY);
        $origin = parse_url (home_url ('/'));
        $return = ($origin['scheme'] ?? 'https') . '://' . ($origin['host'] ?? '')
            . (isset ($origin['port']) ? ':' . $origin['port'] : '')
            . '/' . ltrim (is_string ($path) ? $path : '', '/')
            . (is_string ($query) ? '?' . $query : '');
        if (!wp_safe_redirect (wp_login_url ($return))) {status_header (403);}
        return false;
    }
}
