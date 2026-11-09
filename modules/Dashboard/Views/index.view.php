<?php
if (!defined('ABSPATH')) { exit; }
$display_name = get_current_user()->display_name ?? null;
?>
<h1><?php esc_html_e('Dashboard', 'radykfw'); ?></h1>
<p><?php esc_html_e('Welcome to your dashboard,', 'radykfw'); ?> <?php echo $display_name === null ? esc_html__('User', 'radykfw') : esc_html($display_name); ?>.</p>
<p><?php esc_html_e('URL:', 'radykfw'); ?> /dashboard</p>
