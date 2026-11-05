<?php
/**
 * Welcome Home View
 * File: home.view.php
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<h1>Welcome Home</h1>
<p>This is the home page of the Welcome module.</p>
<p>URL: /welcome/home</p>
<?php if (!empty($args)): ?>
<p>Arguments: <?php echo esc_html(implode(', ', $args)); ?></p>
<?php endif; ?>