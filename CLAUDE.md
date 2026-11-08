
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

---