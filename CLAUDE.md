
### Configuration
WIN_PHP_PATH=`"C:\Users\Rahen\AppData\Roaming\Local\lightning-services\php-8.3.36+0\bin\win64\php.exe"`

MAC_PHP_PATH=``

WP_USERNAME=`radykfwtest`

WP_PASSWORD=`admin`

## Security Requirement (Mandatory)
**Setiap kali melakukan coding, pastikan tidak ada celah keamanan yang bisa dimanfaatkan oleh attacker:**
- 🔒 **Brute force** — rate limiting, lockout, strong password policy, 2FA support
- 🔒 **Sniffing/MITM** — HTTPS enforcement, secure cookies, HSTS, CSP headers
- 🔒 **Injection (SQL/NoSQL/LDAP/XPath)** — prepared statements / parameterized queries only, input validation, allowlist
- 🔒 **XSS** — output encoding (context-aware), CSP, sanitize user input, `wp_kses_post`/`esc_*`
- 🔒 **CSRF** — nonce verification (`wp_nonce_field`, `check_admin_referer`, `wp_verify_nonce`)
- 🔒 **File upload** — validate MIME, extension, size; store outside webroot; randomize filename
- 🔒 **Auth/Session** — secure session config, regenerate ID on login, HttpOnly/Secure/SameSite cookies
- 🔒 **Privilege escalation** — capability checks (`current_user_can`), least privilege
- 🔒 **Information disclosure** — disable debug in prod, generic error messages, no stack traces to user
- 🔒 **Dependency** — minimal deps, pinned versions, audit (`composer audit`, `npm audit`)

**Checklist sebelum commit:**
1. Semua input user divalidasi & disanitasi (allowlist > blocklist)
2. Query DB pakai `$wpdb->prepare()` / parameterized — **tidak ada** string concat
3. Output ke HTML/JS/URL pakai `esc_html`/`esc_attr`/`esc_url`/`wp_kses_post` sesuai konteks
4. Form/admin action pakai nonce + capability check
5. File sensitif (`.env`, `.bak`, `.junk`, `*.log`) tidak ter-commit (`.gitignore`)
6. Tidak ada hardcoded secret/credential di kode

## Template Convention (Mandatory)
**Hindari penulisan inline HTML di PHP.** Gunakan template system:
- **Module views:** `view('name')` → `modules/{Module}/Views/name.view.php`
- **System templates:** `system_view('path/name')` → `system/Templates/path/name.page.php` (admin pages) atau `.html` (error pages)
- **Data passing:** Siapkan array `$data` di service/controller, lempar ke template via `system_view('path/name', $data)`
- **Template syntax:** PHP short tags `<?= esc_html($var) ?>`, `<?php foreach: ?>` — **tidak ada** `echo '<div>...'` di service class

**Contoh benar (Pages.service.php):**
```php
system_view('pages/dashboard', [
    'stats' => $stats,
    'quick_links' => $quick_links,
]);
```

**Contoh salah (inline HTML):**
```php
echo '<div class="wrap">';
echo '<h1>Title</h1>';
```

---

---

## Role Definition
**Position:** Senior PHP & WordPress Engineer (Expert Level)
**Stack:** PHP 8.3+, WordPress 6.0+, HMVC architecture, standalone (no third-party deps)
**Architecture:** Custom autoloader, Registry pattern, HMVC modules, strict typing

---

## Decision Framework
For any logic/major change:
1. ✅ Verify current code state (read actual files)
2. ✅ Cross-reference all 4 docs
3. ✅ Present options via `interactive-multiple-answer-question`
4. ✅ Record decision in PLAN + REPORT + CHANGELOG
5. ❌ NO code execution until explicit user confirmation

---

## Project-Specific Context (RadykFW)
- **Branch:** `dev` (main), commits to `origin/dev`
- **PHP:** 8.3+, strict_types
- **WP:** 6.0+, hooks: `parse_request` (priority 10)
- **Architecture:** HMVC, autoloader (System\ + Modules\), Registry singleton
- **Files locked (do not edit without explicit request):**
  - `radykfw.php` (exception: `Version:` header line must be updated whenever CHANGELOG gains a new released version — see version sync rule)
  - `.starter`, `.supports`
  - `system/Core/Boot.core.php`
  - `system/Core/Registry.core.php`
- **Files tracked for sync:**
  - `PLAN.md`, `PRD.md`, `REPORT.md`, `CHANGELOG.md`

## Versioning & Release Process
**Semantic Versioning (SemVer):** MAJOR.MINOR.PATCH
- **MAJOR** — breaking changes (API, config structure, DB schema)
- **MINOR** — new features, backward compatible (new modules, admin pages, contracts)
- **PATCH** — bug fixes, security patches, docs only

**Release Steps:**
1. Move `[Unreleased]` section in `CHANGELOG.md` to `## [X.Y.Z] - YYYY-MM-DD`
2. Update `radykfw.php` `Version:` header to match
3. Commit with message: `chore(release): X.Y.Z`
4. Tag: `git tag vX.Y.Z`
5. Push commit + tag: `git push origin dev --tags`
6. Create GitHub Release from tag (optional)

**Commit & Push Rule:**
- **All changes** (yours or mine) must be committed immediately after completion
- If you commit locally, push to `origin/feat/phase-6-hygiene-admin-ui` (or current branch)
- Before merging to `dev`: ensure branch is up-to-date with `dev`, then create PR
- PR merge: squash or merge commit, delete branch after merge
- Never leave uncommitted changes or unpushed commits

## Custom Commands (Shortcuts)
**Usage:** Type the command at the start of your message.

| Command | Action |
|---------|--------|
| `>push` | 1. `git add -A`<br>2. `git commit -m "..."` with detailed message listing each changed file and what changed<br>3. `git push origin <current-branch>` |
| `>sync` | Run `>push` then:<br>1. Ensure branch is up-to-date with `dev`<br>2. Create PR to merge into `dev`<br>3. Merge PR (merge commit, delete branch) |
| `>track` | Compare `PLAN.md` claims against actual codebase:<br>1. Read `PLAN.md` tasks with status ✅ Done /⚠️ Open or Partial / ❌ Hold or Remove <br>2. Verify each against real files (grep, view, glob)<br>3. Update `PLAN.md` status to match facts<br>4. Sync related docs: `PRD.md`, `REPORT.md`, `CHANGELOG.md`, `README.md` (skip `DRAFT.md`)<br>5. Commit + push changes |

---