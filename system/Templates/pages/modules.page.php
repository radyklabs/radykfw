<?php declare (strict_types=1);

/**
 * Modules Page Template
 * File: system/Templates/pages/modules.page.php
 *
 * @var array $stats
 * @var array $modules
 * @var array $routes
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
                    <td><?= esc_html__ ('Active Modules', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['active_modules'] ?? 0) ?></td>
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

    <!-- Active Modules -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2><?= esc_html__ ('Active Modules', 'radykfw') ?></h2>
        <p><?= esc_html__ ('Currently loaded modules with their controllers and views.', 'radykfw') ?></p>
        <div class="radyk-feature-row">
            <?php foreach ($modules as $module): ?>
                <div class="main-item radyk-feature">
                    <div class="item-paragraf">
                        <h3><span aria-hidden="true"><?= esc_html ($module['icon']) ?></span> <?= esc_html ($module['name']) ?></h3>
                        <p><?= esc_html ($module['description']) ?></p>
                        <small><?= esc_html ($module['routes']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Registered Routes -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2><?= esc_html__ ('Registered Routes', 'radykfw') ?></h2>
        <p><?= esc_html__ ('Explicit route aliases from routing.conf.php', 'radykfw') ?></p>
        <?php if (empty ($routes)): ?>
            <p><?= esc_html__ ('No routes configured.', 'radykfw') ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?= esc_html__ ('Method', 'radykfw') ?></th>
                        <th><?= esc_html__ ('Path', 'radykfw') ?></th>
                        <th><?= esc_html__ ('Handler', 'radykfw') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><code><?= esc_html ($route['method']) ?></code></td>
                            <td><code><?= esc_html ($route['path']) ?></code></td>
                            <td><code><?= esc_html ($route['handler']) ?></code></td>
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