# Project Rules

## WordPress Text Domain

- Keep the text domain as a string literal (for example, `'radykfw'`) in WordPress gettext calls such as `__()`, `_e()`, `esc_html__()`, and `esc_html_e()`. WordPress translation extraction tools expect a statically identifiable domain; do not replace it with a runtime expression.
- For runtime operations that need the plugin's domain—such as `load_plugin_textdomain()` or locale filters—use the project's established source of truth. `plugin_meta('Text Domain')` reads the plugin header during bootstrap, and `Registry::get('textdomain')` exposes the initialized value elsewhere. A class constant is also appropriate when a service needs a fixed domain locally, provided it stays consistent with the plugin header.
- Do not repeatedly parse plugin metadata at call sites or use `Registry::get()` as the domain argument to gettext calls. Keep translation catalogs and extraction configuration aligned with the literal domain.
