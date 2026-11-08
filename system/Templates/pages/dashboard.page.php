<?php declare (strict_types=1);

/**
 * Dashboard Page Template
 * File: system/Templates/pages/dashboard.page.php
 *
 * @var array $stats
 * @var array $quick_links
 */

?>
<div class="wrap radyk-wrap">
    <h1>RADYK Labs — Dashboard</h1>

    <!-- Quick Stats -->
    <div class="radyk-section">
        <h2>Quick Stats</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Routing Mode</td>
                    <td><?= esc_html ($stats['routing_mode'] ?? 'Unknown') ?></td>
                </tr>
                <tr>
                    <td>Auto-discover Modules</td>
                    <td><?= esc_html ($stats['auto_discover'] ?? 'Unknown') ?></td>
                </tr>
                <tr>
                    <td>Total Aliases</td>
                    <td><?= esc_html ($stats['total_aliases'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Admin Notices</td>
                    <td><?= esc_html ($stats['admin_notices'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Helper Skips</td>
                    <td><?= esc_html ($stats['helper_skips'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Quick Links -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2>Quick Links</h2>
        <p>
            <?php foreach ($quick_links as $link): ?>
                <a href="<?= esc_url ($link['url']) ?>" class="radyk-btn<?= $link['secondary'] ? ' secondary' : '' ?>">
                    <?= esc_html ($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </p>
    </div>
</div>