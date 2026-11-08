<?php declare (strict_types=1);

/**
 * Modules Page Template
 * File: system/Templates/pages/modules.page.php
 *
 * @var array $stats
 * @var array $aliases
 * @var int $admin_notices_count
 * @var int $helper_skips_count
 */

?>
<div class="wrap radyk-wrap">
    <h1>RADYK Labs — Modules</h1>

    <!-- Overview Section -->
    <div class="radyk-section">
        <h2>Overview</h2>
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
                    <td><?= esc_html ($admin_notices_count ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Helper Skips</td>
                    <td><?= esc_html ($helper_skips_count ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Routing Aliases Section -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2>Routing Aliases</h2>
        <?php if (empty ($aliases)): ?>
            <p>No aliases configured.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Method / Path</th>
                        <th>Target</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aliases as $key => $target): ?>
                        <tr>
                            <td><code><?= esc_html ($key) ?></code></td>
                            <td><code><?= esc_html ($target) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Debug Log Section -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2>Debug Log</h2>
        <p>Admin notices registry: <?= esc_html ($admin_notices_count ?? 0) ?> notice(s) registered.</p>
        <p>Helper skips: <?= esc_html ($helper_skips_count ?? 0) ?> file(s) skipped.</p>
    </div>
</div>