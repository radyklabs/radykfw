<?php
if (!defined('ABSPATH')) { exit; }
?>
<h1>Dashboard</h1>
<p>Welcome to your dashboard, <?php echo esc_html(get_current_user()->display_name ?? 'User'); ?>.</p>
<p>URL: /dashboard</p>
