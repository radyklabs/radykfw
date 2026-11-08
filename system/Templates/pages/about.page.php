<?php declare (strict_types=1);

/**
 * About Page Template
 * File: system/Templates/pages/about.page.php
 *
 * @var array $page_title
 * @var array $requirements
 * @var array $features
 * @var array $resources
 * @var array $final_notes
 * @var string $license_text
 */

?>
<div class="wrap radyk-wrap">
    <header>
        <h1><?= esc_html ($page_title ?? 'About RADYK') ?></h1>
    </header>

    <!-- 1. System Requirements -->
    <section id="requirements" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html ('System Requirements') ?></h2>
                <p><?= esc_html ('What you need to run RADYK comfortably.') ?></p>
            </div>
            <div class="card-body">
                <ul class="radyk-ul">
                    <?php foreach ($requirements as $req): ?>
                        <li><strong><?= esc_html ($req['name']) ?>:</strong> <?= esc_html ($req['version']) ?> <?= isset($req['badge']) ? '<span class="ds-badge ds-badge-success ds-badge-sm">' . esc_html($req['badge']) . '</span>' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- 2. Features -->
    <section id="features" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html ('Features') ?></h2>
                <p><?= esc_html ('A quick tour of what RADYK offers for your WordPress site.') ?></p>
            </div>
            <div class="card-body">
                <?php foreach (array_chunk($features, 2) as $row): ?>
                    <div class="radyk-feature-row">
                        <?php foreach ($row as $feature): ?>
                            <div class="main-item radyk-feature">
                                <div class="item-paragraf">
                                    <h3><span aria-hidden="true"><?= esc_html ($feature['icon']) ?></span> <?= esc_html ($feature['title']) ?></h3>
                                    <p><?= esc_html ($feature['desc']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 3. Online Resources -->
    <section id="resources" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html ('Online Resources') ?></h2>
                <p><?= esc_html ('If you have a question not covered in this overview, take advantage of the resources below.') ?></p>
            </div>
            <div class="card-body">
                <dl>
                    <?php foreach ($resources as $resource): ?>
                        <dt><a href="<?= esc_url ($resource['url']) ?>"><?= esc_html ($resource['title']) ?></a></dt>
                        <dd><?= esc_html ($resource['desc']) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </section>

    <!-- 4. Final Notes -->
    <section id="final-notes" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html ('Final Notes') ?></h2>
            </div>
            <div class="card-body">
                <ul class="radyk-ul">
                    <?php foreach ($final_notes as $note): ?>
                        <li><?= esc_html ($note) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- 5. License -->
    <section id="license" class="radyk-section">
        <div class="card">
            <div class="card-header">
                <h2><?= esc_html ('License') ?></h2>
            </div>
            <div class="card-body">
                <p><?= esc_html ($license_text) ?></p>
            </div>
        </div>
    </section>
</div>