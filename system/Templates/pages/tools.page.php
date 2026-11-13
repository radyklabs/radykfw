<?php declare (strict_types=1);

/**
 * Tools Page Template
 * File: system/Templates/pages/tools.page.php
 *
 * @var string|null $page_title
 * @var array $branding
 * @var array $features
 */

?>
<div class="wrap">
    <header>
        <h1><?= esc_html ($page_title ?? __('Tools', 'radykfw')) ?></h1>
    </header>

    <!-- Branding -->
    <section id="branding">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html__ ('Radyk Labs', 'radykfw') ?></h2>
                <p><?= esc_html__ ('Shared admin environment for Radyk Labs products.', 'radykfw') ?></p>
            </div>
            <div class="card-body">
                <p>
                    <?= esc_html ($branding['description']) ?>
                </p>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <?php foreach ($branding['products'] as $product) { ?>
                            <tr>
                                <th scope="row"><?= esc_html ($product['name']) ?></th>
                                <td><?= esc_html ($product['role']) ?></td>
                                <td><span class="ds-badge"><?= esc_html ($product['status']) ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Feature roadmap -->
    <section id="features">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html__ ('Planned Features', 'radykfw') ?></h2>
                <p><?= esc_html__ ('Coming soon to Radyk Labs admin.', 'radykfw') ?></p>
            </div>
            <div class="card-body">
                <ul class="ul-disc">
                    <?php foreach ($features as $feature) { ?>
                        <li>
                            <strong><?= esc_html ($feature['title']) ?></strong>
                            &mdash; <?= esc_html ($feature['desc']) ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </section>
</div>
