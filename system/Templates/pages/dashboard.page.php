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
 * @var array $modules
 * @var array $routes
 */

?>
<div class="wrap radyk-wrap">
    <header>
        <h1><?= esc_html ($page_title ?? __('Overview', 'radykfw')) ?></h1>
        <span class="ds-badge"><?= esc_html ($version_badge ?? 'RadykFW 1.0.9') ?></span>
    </header>

    <?php if ($maintenance_mode): ?>
    <section id="maintenance">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h5><?= esc_html__ ('Maintenance Mode Active', 'radykfw') ?></h5>
                    <p><?= esc_html__ ('Visitors see a maintenance notice. Disable it in', 'radykfw') ?> <a href="<?= esc_url (admin_url ('options-general.php')) ?>"><?= esc_html__ ('Settings', 'radykfw') ?></a>.</p>
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
                    <h2><?= esc_html (sprintf (esc_html__ ('%1$s, %2$s 👋🏼', 'radykfw'), $greeting, $user_name)) ?></h2>
                    <p><?= esc_html__ ('Welcome to RadykFW — an enterprise-grade HMVC framework for WordPress.', 'radykfw') ?></p>
                </hgroup>
            </div>
            <div class="card-body">
                <div class="main-item">
                    <div class="item-paragraf">
                                    <p><?= esc_html__ ('RadykFW is a very special project to us. Every module you build, every route you define, and every line of code you ship adds something unique to the mix — and together we create something beautiful. Thousands of hours have gone into RadykFW, and we are dedicated to making it better every day. Thank you for making it part of your world.', 'radykfw') ?></p>
                        <p style="text-align: right">— <?= esc_html ('The RADYK Labs Team') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Active Modules -->
    <section id="active-modules" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h2><?= esc_html__ ('Active Modules', 'radykfw') ?></h2>
                    <p><?= esc_html__ ('Currently loaded modules with their controllers and views.', 'radykfw') ?></p>
                </hgroup>
            </div>
            <div class="card-body">
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
        </div>
    </section>

    <!-- Quick Actions -->
    <section id="quick-actions">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h2><?= esc_html__ ('Quick Actions', 'radykfw') ?></h2>
                    <p><?= esc_html__ ('Jump straight to the admin pages you use the most.', 'radykfw') ?></p>
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

    <!-- Registered Routes -->
    <section id="registered-routes" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <hgroup class="head-item-title">
                    <h2><?= esc_html__ ('Registered Routes', 'radykfw') ?></h2>
                    <p><?= esc_html__ ('Explicit route aliases from routing.conf.php', 'radykfw') ?></p>
                </hgroup>
            </div>
            <div class="card-body">
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
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <div class="radyk-section" style="margin-top:20px;">
        <h2><?= esc_html__ ('Quick Stats', 'radykfw') ?></h2>
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
                    <td><?= esc_html (count($modules)) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Admin Notices', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['admin_notices'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td><?= esc_html__ ('Helper Skips', 'radykfw') ?></td>
                    <td><?= esc_html ($stats['helper_skips'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>