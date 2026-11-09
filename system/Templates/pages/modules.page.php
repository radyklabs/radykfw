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
<div class="wrap">
    <h1><?= esc_html__ ('RADYK Labs — Modules', 'radykfw') ?></h1>

    <!-- Overview Section -->
    <div class="radyk-section">
        <h2><?= esc_html__ ('Overview', 'radykfw') ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?= esc_html__ ('Setting', 'radykfw') ?></th>
                    <th><?= esc_html__ ('Value', 'radykfw') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= esc_html__ ('Routing Mode', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['routing_mode'] ?? __('Unknown', 'radykfw')) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Auto-discover Modules', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['auto_discover'] ?? __('Unknown', 'radykfw')) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Total Aliases', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['total_aliases'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Admin Notices', 'radykfw') ?></td>
                    <td><?= esc_html ($admin_notices_count ?? 0) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Helper Skips', 'radykfw') ?></td>
                    <td><?= esc_html ($helper_skips_count ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Routing Aliases Section -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2><?= esc_html__ ('Routing Aliases', 'radykfw') ?></h2>
        <?php if (empty ($aliases)): ?>
            <p><?= esc_html__ ('No aliases configured.', 'radykfw') ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?= esc_html__ ('Method / Path', 'radykfw') ?></th>
                        <th><?= esc_html__ ('Target', 'radykfw') ?></th>
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
        <h2><?= esc_html__ ('Debug Log', 'radykfw') ?></h2>
        <p><?= sprintf (esc_html__ ('Admin notices registry: %s notice(s) registered.', 'radykfw'), esc_html ($admin_notices_count ?? 0)) ?></p>
        <p><?= sprintf (esc_html__ ('Helper skips: %s file(s) skipped.', 'radykfw'), esc_html ($helper_skips_count ?? 0)) ?></p>
    </div>
</div>