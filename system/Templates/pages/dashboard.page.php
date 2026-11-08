<?php declare (strict_types=1);

/**
 * Dashboard Page Template (Overview)
 * File: system/Templates/pages/dashboard.page.php
 *
 * @var array $stats
 * @var array $quick_actions
 * @var string $version_badge
 * @var bool $maintenance_mode
 * @var string $greeting
 * @var string $user_name
 */

?>
<div class="wrap radyk-wrap">
    <header>
        <h1><?= esc_html ($page_title ?? 'Overview') ?></h1>
        <span class="ds-badge"><?= esc_html ($version_badge ?? 'RADYK FW') ?></span>
    </header>

    <?php if ($maintenance_mode): ?>
    <section id="maintenance">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h5><?= esc_html ('Maintenance Mode Active') ?></h5>
                    <p><?= esc_html ('Visitors see a maintenance notice. Disable it in') ?> <a href="<?= esc_url (admin_url ('options-general.php')) ?>"><?= esc_html ('Settings') ?></a>.</p>
                </hgroup>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Greetings personalization -->
    <section id="overview">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h2><?= esc_html (sprintf ('%s, %s 👋🏼', $greeting, $user_name)) ?></h2>
                    <p><?= esc_html ('Welcome to RADYK — an independent HMVC framework for WordPress.') ?></p>
                </hgroup>
            </div>
            <div class="card-body">
                <div class="main-item">
                    <div class="item-paragraf">
                        <p><?= esc_html ('RADYK is a very special project to us. Every module you build, every extension you enable, and every line of code you ship adds something unique to the mix — and together we create something beautiful. Thousands of hours have gone into RADYK, and we are dedicated to making it better every day. Thank you for making it part of your world.') ?></p>
                        <p style="text-align: right">— <?= esc_html ('The RADYK Labs Team') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section id="quick-actions">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h2><?= esc_html ('Quick Actions') ?></h2>
                    <p><?= esc_html ('Jump straight to the admin pages you use the most.') ?></p>
                </hgroup>
            </div>
            <div class="card-body">
                <div class="main-item">
                <div class="radyk-feature-row radyk-quick-row">
                    <?php foreach ($quick_actions as $action): ?>
                        <a class="main-item radyk-feature radyk-quick-card<?= $action['primary'] ? ' is-primary' : '' ?>" href="<?= esc_url ($action['url']) ?>">
                            <div class="item-paragraf">
                                <h3><span aria-hidden="true"><?= esc_html ($action['icon']) ?></span> <?= esc_html ($action['title']) ?></h3>
                                <p><?= esc_html ($action['desc']) ?></p>
                                <span class="radyk-quick-go" aria-hidden="true">→</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2><?= esc_html ('Quick Stats') ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?= esc_html ('Setting') ?></th>
                    <th><?= esc_html ('Value') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= esc_html ('Routing Mode') ?></td>
                    <td><?= esc_html ($stats['routing_mode'] ?? 'Unknown') ?></td>
                </tr>
                <tr>
                    <td><?= esc_html ('Auto-discover Modules') ?></td>
                    <td><?= esc_html ($stats['auto_discover'] ?? 'Unknown') ?></td>
                </tr>
                <tr>
                    <td><?= esc_html ('Total Aliases') ?></td>
                    <td><?= esc_html ($stats['total_aliases'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html ('Admin Notices') ?></td>
                    <td><?= esc_html ($stats['admin_notices'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html ('Helper Skips') ?></td>
                    <td><?= esc_html ($stats['helper_skips'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>