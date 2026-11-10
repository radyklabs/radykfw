<?php declare (strict_types=1);

namespace System\Services;

use System\Contracts\MiddlewareInterface;

final class AuthMiddleware implements MiddlewareInterface
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
