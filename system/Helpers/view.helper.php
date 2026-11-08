<?php
/**
 * RadykFW View Helpers
 * File: system/Helpers/view.helper.php
 *
 * Module views (strict casing rule):
 *   view('home')            -> modules/{current}/Views/home.view.php
 *   view('folder.home')     -> modules/{current}/Views/folder/home.view.php
 *   view('Login.home')      -> modules/Login/Views/home.view.php
 *   view('Login.folder.name')-> modules/Login/Views/folder/name.view.php
 *
 * System templates:
 *   system_view('errors/404.error') -> system/Templates/errors/404.error.html
 *   system_view('pages/dashboard')  -> system/Templates/pages/dashboard.page.php
 *
 * STRICT RULE (module views):
 *   First part CAPITALIZED  = explicit module (e.g. 'Login', 'Welcome')
 *   First part lowercase    = subfolder of current module (e.g. 'folder', 'partials')
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a MODULE view with data.
 *
 * @param string $view   Module view identifier
 *                        'home'            -> modules/{current}/Views/home.view.php
 *                        'folder.home'     -> modules/{current}/Views/folder/home.view.php
 *                        'Login.home'      -> modules/Login/Views/home.view.php
 *                        'Login.folder.name'-> modules/Login/Views/folder/name.view.php
 * @param array  $data   Data to pass to view
 * @param bool   $return Return content instead of echo
 * @return string|null
 */
function view(string $view, array $data = [], bool $return = false): ?string {
    $viewFile = resolve_module_view($view);

    $scope = [];
    foreach ($data as $k => $v) {
        $scope[$k] = $v;
    }

    ob_start();
    extract($scope, EXTR_SKIP);
    include $viewFile;
    $content = ob_get_clean();

    if ($return) {
        return $content;
    }

    echo $content;
    return null;
}

/**
 * Render a SYSTEM template with data.
 *
 * @param string $template Template path under system/Templates/
 *                         'errors/404.error' -> system/Templates/errors/404.error.html
 *                         'components/button' -> system/Templates/components/button.component.php
 * @param array  $data   Data to pass to template
 * @param bool   $return Return content instead of echo
 * @return string|null
 */
function system_view(string $template, array $data = [], bool $return = false): ?string {
    $templateFile = resolve_system_template($template);

    $scope = [];
    foreach ($data as $k => $v) {
        $scope[$k] = $v;
    }

    ob_start();
    extract($scope, EXTR_SKIP);
    include $templateFile;
    $content = ob_get_clean();

    if ($return) {
        return $content;
    }

    echo $content;
    return null;
}

/**
 * Resolve a module view identifier to an absolute filesystem path.
 *
 * @param string $view Module view identifier
 * @return string Absolute path to view file
 */
function resolve_module_view(string $view): string {
    $parts = explode('.', $view);
    $current = current_module();

    if (count($parts) >= 2) {
        $first = $parts[0];

        if (ctype_upper($first[0])) {
            // Explicit module: Module[subfolder].Name
            $module    = $first;
            $viewName  = end($parts);
            $subfolder = count($parts) > 2 ? implode('/', array_slice($parts, 1, -1)) : '';
            $file = module_path($module) . '/Views/' . ($subfolder ? $subfolder . '/' : '') . $viewName . '.view.php';
            if (file_exists($file)) {return $file;}
            return system_path('Templates/errors/404.error.html');
        }

        // Lowercase first = all parts under current module
        if ($current) {
            $viewName  = end($parts);
            $subfolder = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : '';
            $file = module_path($current) . '/Views/' . ($subfolder ? $subfolder . '/' : '') . $viewName . '.view.php';
            if (file_exists($file)) {return $file;}
        }
    } elseif ($current) {
        // Single part: 'home' -> current module view
        $file = module_path($current) . '/Views/' . $parts[0] . '.view.php';
        if (file_exists($file)) {return $file;}
    }

    return system_path('Templates/errors/404.error.html');
}

/**
 * Resolve a system template identifier to an absolute filesystem path.
 *
 * @param string $template Template identifier (e.g. 'errors/404.error')
 * @return string Absolute path to template file
 */
function resolve_system_template(string $template): string
{
    // Check .page.php first (RadykFW page template convention)
    $file = system_path ('Templates/' . $template . '.page.php');
    if (file_exists ($file)) {return $file;}

    // Fallback: .html then .php
    $file = system_path ('Templates/' . $template . '.html');
    if (file_exists ($file)) {return $file;}
    $file = system_path ('Templates/' . $template . '.php');
    if (file_exists ($file)) {return $file;}

    // Not found — fall through to 404
    return system_path ('Templates/errors/404.error.html');
}