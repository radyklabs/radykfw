<?php
/**
 * Welcome Door View
 * File: door.view.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1><?php esc_html_e('Welcome Door', 'radykfw'); ?></h1>
<p><?php esc_html_e('This is the door page with parameter support.', 'radykfw'); ?></p>
<p><?php esc_html_e('URL:', 'radykfw'); ?> /welcome/home/door/param1/param2</p>
<?php if (!empty($args)): ?>
<p><?php esc_html_e('Arguments:', 'radykfw'); ?> <?php echo esc_html(implode(', ', $args)); ?></p>
<?php endif; ?>