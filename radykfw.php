<?php
/**
 * Plugin Name: RadykFW
 * Plugin URI: https://radyklabs.com/radykfw
 * Description: WordPress HMVC Framework with custom routing. Intercepts requests and routes to modules before WordPress native handling.
 * Version: 1.0.1
 * Author: RADYK Labs
 * Author URI: https://radyklabs.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: radykfw
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Tested up to: 7.1
 * Network: false
 */

// Start session for flash messages and auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access
if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

$heading = 'Internal System Error';
$message = 'An unexpected error occurred. Please contact the Administrator.';

if (!is_callable ('implode', false, $prep)
    || !is_callable ('define', false, $set)
    || !is_callable ('call_user_func', false, $do))
{
    wp_die ($message, $heading);
}

$set ('sep', DIRECTORY_SEPARATOR);
$set ('keyfile', __FILE__);

$starter = $prep ( '.', [plugin_dir_path (keyfile), 'starter']);
if (is_readable ($starter)) {require $starter;}

// process ('Boot');
try {$do ('initiate_process', 'Boot');} catch (\Throwable $e){ wp_die ($e);}