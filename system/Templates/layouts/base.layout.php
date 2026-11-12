<?php
/**
 * Base Layout
 * File: system/Templates/layouts/base.drip
 *
 * Shared layout for Drip templates. Uses @yield for content sections.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html {{ language_attributes() }}>
<head>
    <meta charset="{{ esc_attr(get_bloginfo('charset')) }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '{{ esc_html__('RadykFW', 'radykfw') }}')</title>
    {{ wp_head() }}
    @stack('styles')
</head>
<body {{ body_class() }}>
    @yield('content')
    @stack('scripts')
    {{ wp_footer() }}
</body>
</html>