<?php declare (strict_types=1);

/**
 * RadykFW Settings Page
 *
 * @var array $site_settings
 * @var array $user_settings
 * @var array<string, string> $locale_options
 * @var string $saved_scope
 */

?>
<div class="wrap">
    <h1><?= esc_html__ ('RadykFW Settings', 'radykfw') ?></h1>

    <?php if ($saved_scope === 'site'): ?>
        <div class="notice notice-success is-dismissible"><p><?= esc_html__ ('Site settings saved.', 'radykfw') ?></p></div>
    <?php elseif ($saved_scope === 'user'): ?>
        <div class="notice notice-success is-dismissible"><p><?= esc_html__ ('Your preferences saved.', 'radykfw') ?></p></div>
    <?php endif; ?>

    <section class="radyk-section">
        <h2><?= esc_html__ ('Site-wide settings', 'radykfw') ?></h2>
        <p><?= esc_html__ ('These options apply to the whole site. Settings stored in settings.conf.php remain read-only and can be overridden here.', 'radykfw') ?></p>
        <form method="post" action="<?= esc_url (admin_url ('admin-post.php')) ?>">
            <input type="hidden" name="action" value="radykfw_save_settings">
            <input type="hidden" name="scope" value="site">
            <?php wp_nonce_field ('radykfw_save_site_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="radykfw-admin-locale"><?= esc_html__ ('Admin locale', 'radykfw') ?></label></th>
                    <td>
                        <select id="radykfw-admin-locale" name="config[admin_locale]">
                            <option value="" <?= selected ($site_settings['admin_locale_override'], '', false) ?>><?= esc_html__ ('Use inherited locale', 'radykfw') ?></option>
                            <?php foreach ($locale_options as $locale => $label): ?>
                                <option value="<?= esc_attr ($locale) ?>" <?= selected ($site_settings['admin_locale_override'], $locale, false) ?>><?= esc_html ($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?= sprintf (esc_html__ ('Current effective site locale: %s', 'radykfw'), esc_html ($site_settings['admin_locale_effective'] ?: __('WordPress default', 'radykfw'))) ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?= esc_html__ ('Routing priority', 'radykfw') ?></th>
                    <td>
                        <input type="hidden" name="config[routing.native]" value="0">
                        <label>
                            <input type="checkbox" name="config[routing.native]" value="1" <?= checked ($site_settings['routing.native'], true, false) ?>>
                            <?= esc_html__ ('Let WordPress handle native content before RadykFW routes.', 'radykfw') ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?= esc_html__ ('Module auto-discovery', 'radykfw') ?></th>
                    <td>
                        <input type="hidden" name="config[modules.auto_discover]" value="0">
                        <label>
                            <input type="checkbox" name="config[modules.auto_discover]" value="1" <?= checked ($site_settings['modules.auto_discover'], true, false) ?>>
                            <?= esc_html__ ('Allow routing to discover module controllers without an explicit alias.', 'radykfw') ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button (__('Save site settings', 'radykfw')); ?>
        </form>
    </section>

    <section class="radyk-section">
        <h2><?= esc_html__ ('My preferences', 'radykfw') ?></h2>
        <p><?= esc_html__ ('Personal preferences are stored for your WordPress user account and override the site-wide value.', 'radykfw') ?></p>
        <form method="post" action="<?= esc_url (admin_url ('admin-post.php')) ?>">
            <input type="hidden" name="action" value="radykfw_save_settings">
            <input type="hidden" name="scope" value="user">
            <?php wp_nonce_field ('radykfw_save_user_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="radykfw-user-locale"><?= esc_html__ ('Personal admin locale', 'radykfw') ?></label></th>
                    <td>
                        <select id="radykfw-user-locale" name="config[admin_locale]">
                            <option value="" <?= selected ($user_settings['admin_locale_override'], '', false) ?>><?= esc_html__ ('Use site and WordPress locale', 'radykfw') ?></option>
                            <?php foreach ($locale_options as $locale => $label): ?>
                                <option value="<?= esc_attr ($locale) ?>" <?= selected ($user_settings['admin_locale_override'], $locale, false) ?>><?= esc_html ($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?= sprintf (esc_html__ ('Your current effective locale: %s', 'radykfw'), esc_html ($user_settings['admin_locale_effective'] ?: __('WordPress default', 'radykfw'))) ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button (__('Save my preferences', 'radykfw')); ?>
        </form>
    </section>

    <section class="radyk-section">
        <h2><?= esc_html__ ('Backup', 'radykfw') ?></h2>
        <p><?= esc_html__ ('Download a versioned JSON backup of site settings and your own preferences. Secret values are excluded.', 'radykfw') ?></p>
        <form method="post" action="<?= esc_url (admin_url ('admin-post.php')) ?>">
            <input type="hidden" name="action" value="radykfw_export_settings">
            <?php wp_nonce_field ('radykfw_export_settings'); ?>
            <?php submit_button (__('Download settings backup', 'radykfw'), 'secondary'); ?>
        </form>
    </section>
</div>
