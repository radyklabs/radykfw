<?php declare(strict_types=1);

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
