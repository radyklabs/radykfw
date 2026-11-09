<?php
/**
 * Welcome Home View
 * File: home.view.php
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<h1><?php esc_html_e('Welcome Home', 'radykfw'); ?></h1>
<p><?php esc_html_e('This is the home page of the Welcome module.', 'radykfw'); ?></p>
<p><?php esc_html_e('URL:', 'radykfw'); ?> /welcome/home</p>
<?php if (!empty($args)): ?>
<p><?php esc_html_e('Arguments:', 'radykfw'); ?> <?php echo esc_html(implode(', ', $args)); ?></p>
<?php endif; ?>