# Project Rules

## WordPress Text Domain

- Keep the text domain as a string literal (for example, `'radykfw'`) in WordPress gettext calls such as `__()`, `_e()`, `esc_html__()`, and `esc_html_e()`. WordPress translation extraction tools expect a statically identifiable domain; do not replace it with a runtime expression.
- For runtime operations that need the plugin's domain—such as `load_plugin_textdomain()` or locale filters—use the project's established source of truth. `plugin_meta('Text Domain')` reads the plugin header during bootstrap, and `Registry::get('textdomain')` exposes the initialized value elsewhere. A class constant is also appropriate when a service needs a fixed domain locally, provided it stays consistent with the plugin header.
- Do not repeatedly parse plugin metadata at call sites or use `Registry::get()` as the domain argument to gettext calls. Keep translation catalogs and extraction configuration aligned with the literal domain.

## WordPress Security Requirements

When coding, account for the relevant security risks and mitigations:

- **Brute force:** rate limiting, lockout, strong password policy, and 2FA support where appropriate.
- **Sniffing/MITM:** HTTPS enforcement, secure cookies, HSTS, and CSP headers where applicable.
- **Injection:** validate and allowlist input; use `$wpdb->prepare()` or parameterized queries, never SQL string concatenation.
- **XSS:** sanitize input and escape output contextually with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.
- **CSRF:** verify nonces for forms and admin actions using WordPress nonce APIs.
- **File uploads:** validate MIME type, extension, and size; store outside the web root where possible and randomize filenames.
- **Authentication/session:** use secure session configuration and cookies (`HttpOnly`, `Secure`, `SameSite`); regenerate session IDs on login.
- **Privilege escalation:** check capabilities with `current_user_can()` and apply least privilege.
- **Information disclosure:** disable debug output in production and avoid exposing stack traces or sensitive errors to users.
- **Dependencies:** minimize dependencies, pin versions, and run relevant dependency audits.

Before committing, verify that user input is validated and sanitized, database queries are parameterized, output is escaped for its context, admin actions have nonce and capability checks, sensitive files are excluded by `.gitignore`, and no credentials or secrets are hardcoded.

## WordPress Template Convention

Avoid writing inline HTML in PHP service classes. Render through the existing template helpers:

- **Module views:** `view('name')` renders `modules/{Module}/Views/name.view.php`.
- **System templates:** `system_view('path/name')` renders `system/Templates/path/name.page.php` for admin pages or `.html` for error pages.
- **Data passing:** prepare a `$data` array in the service/controller and pass it to `system_view('path/name', $data)`.
- **Template syntax:** use PHP short tags such as `<?= esc_html($var) ?>` and alternative control structures such as `<?php foreach: ?>`; do not build markup with `echo '<div>...'` in service classes.

Example:

```php
system_view('pages/dashboard', [
    'stats' => $stats,
    'quick_links' => $quick_links,
]);
```

## RadykFW Project Context

- **Primary branch:** `dev`; the remote is `origin`.
- **Stack:** PHP 8.3+, WordPress 6.0+, strict types, HMVC modules, custom autoloader (`System\` and `Modules\`), and Registry singleton.
- **Routing hook:** `parse_request` (priority 10).
- **Locked files:** do not edit without explicit user request:
  - `radykfw.php`, except its `Version:` header when releasing a version recorded in the changelog.
  - `.starter` and `.supports`.
  - `system/Core/Boot.core.php` and `system/Core/Registry.core.php`.
- **Tracked project documentation:** `docs/PLAN.md`, `docs/PRD.md`, `docs/REPORT.md`, and `docs/CHANGELOG.md`.
- **Role:** work as a senior PHP and WordPress engineer; keep the framework standalone with no third-party runtime dependencies.

## Decision Framework

For logic or major changes:

1. Verify the current code state by reading the actual files.
2. Cross-reference the four tracked project documents.
3. If a decision is needed, ask the user to choose between clear options before proceeding.
4. Record the decision in `docs/PLAN.md`, `docs/REPORT.md`, and `docs/CHANGELOG.md`.
5. Do not execute code or implement the change until the user confirms.

## Versioning and Release Process

Use Semantic Versioning (`MAJOR.MINOR.PATCH`):

- **MAJOR:** breaking changes to APIs, configuration structure, or database schema.
- **MINOR:** backward-compatible features such as modules, admin pages, or contracts.
- **PATCH:** bug fixes, security patches, and documentation-only changes.

Keep changes under `[Unreleased]` during normal development. Promote that section only when the release scope is agreed and frozen, all changes are complete and validated, and the release version/date are known. Determine the version from all changes since the last release, checking completed scope against the actual code, docs, tests, and release history—not only the phase label. For mixed categories, choose the highest impact.

When a planned phase is complete and merged into `dev`, review release readiness and report the recommended version and blockers. Do not promote `[Unreleased]`, update the plugin version, tag, or publish unless the release is ready and the user invokes `>release`. On `>release`, if the scope is not ready, keep `[Unreleased]` and the plugin version unchanged. If ready, move all `[Unreleased]` entries under `## [X.Y.Z] - YYYY-MM-DD`, add a fresh empty `[Unreleased]` section above it, and update the plugin header.

Release steps:

1. Promote `[Unreleased]` and update `radykfw.php`'s `Version:` header.
2. Commit with `chore(release): X.Y.Z`.
3. Tag with `git tag vX.Y.Z`.
4. Push the commit and tag with `git push origin dev --tags`.
5. Optionally create a GitHub Release from the tag.

## Commit and Push

- Commit completed changes immediately; never leave changes uncommitted or commits unpushed.
- Push to the current branch on `origin`.
- Before merging to `dev`, update the branch against `dev` and create a pull request.
- Merge using a squash or merge commit, then delete the merged branch.

## Custom Commands

Use these shortcuts when the user starts a request with the command:

| Command | Action |
|---------|--------|
| `>branch <description>` | Convert the description to a concise lowercase kebab-case branch name with a suitable prefix (`feat/`, `fix/`, `docs/`, or `chore/`); verify it is unused; create and switch to it. |
| `>release` | Review all changes since the latest release and validate readiness; recommend a SemVer version. If ready, promote the changelog, update the plugin version, commit, tag, and push; otherwise report blockers without changing release metadata. |
| `>push` | Inspect status and diff; add a per-file summary under `[Unreleased]` in `docs/CHANGELOG.md`; stage, commit with a detailed message, and push the current branch. |
| `>sync` | Run `>push`, update the branch against `dev`, create and merge a PR into `dev`, and delete the merged branch. |
| `>track` | Compare `docs/PLAN.md` task statuses against the code; update statuses and sync `docs/PRD.md`, `docs/REPORT.md`, `docs/CHANGELOG.md`, and `README.md` (skip `DRAFT.md`); then commit and push. |
