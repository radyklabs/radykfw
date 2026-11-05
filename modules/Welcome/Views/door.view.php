<?php
/**
 * Welcome Door View
 * File: door.view.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1>Welcome Door</h1>
<p>This is the door page with parameter support.</p>
<p>URL: /welcome/home/door/param1/param2</p>
<?php if (!empty($args)): ?>
<p>Arguments: <?php echo esc_html(implode(', ', $args)); ?></p>
<?php endif; ?>