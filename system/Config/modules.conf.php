<?php declare (strict_types=1);

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Modules Configuration
 * File: system/Config/modules.conf.php
 *
 * Access via `get_config('modules.auto_discover')` (dot-notation).
 *
 * @return array<string, mixed>
 */
return [
    // Auto-discover controllers via autoloader on each request
    'auto_discover' => false,
];
