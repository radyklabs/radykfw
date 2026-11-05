# PLAN — RadykFW Development Roadmap

> Dokumen ini merangkum prioritas perbaikan & pengembangan berdasarkan audit REPORT.md + PRD.md.
> Terkini: 2026-11-05 | Branch: `dev` | Commit terbaru: `736ebb5`

---

## 1. Fase 1: Cleanup & Hardening (Prio: High)

### 1.1 Runner.core.php — Dead Code & Latent Bugs
| No | Item | Detail |
|---|------|--------|
| R1 | Hapus `initRegistry()` | Method kosong, tidak pernah dipanggil |
| R2 | Fix `shouldSkipRouting` | Ganti `['wp-login.php', ...]` dengan `['login', 'signup', 'activate']` |
| R3 | Hapus `add_action('template_redirect', fn(){exit;})` | Redundansi, view sudah `exit` sendiri |
| R4 | Hapus `flush_rewrite_rules()` dari `uninstall()` | Riskan di konteks uninstall |
| R5 | Hapus param `$extra_query_vars` dari `handleRouting()` | Tidak dipakai WP |
| R6 | Escape version di `adminNotices()` | `esc_html()` untuk PHP_VERSION / wp_version |

### 1.2 Router.core.php — Dead Methods
| No | Item | Detail |
|---|------|--------|
| R7 | Hapus `dispatchFallback()` param `$params` | Tidak dipakai |
| R8 | Hapus docblock menyesatkan di `dispatchFallback` | Fix docs |
| R9 | Extract `invokeMethod()` helper | Duplikasi ~80% antara `tryDispatch` & `tryDispatchNoRecurse` |

### 1.3 Module Controllers — Inconsistencies
| No | Item | Detail |
|---|------|--------|
| R10 | `Welcome.controller.php` — konfirmasi mati? | Fallback Index menangani semua route Welcome. Jika confirmed dead, hapus. |
| R11 | `...$args` tak terpakai di views | `home.view.php`, `door.view.php` — hapus atau pakai |
| R12 | Footer link hardcode (`/documentation`, `/github`) | Hapus atau buat route |
| R13 | Font `Inter` referensi tapi tak enqueue | Hapus atau tambahkan asset |

---

## 2. Fase 2: Base Model & Database Layer (Prio: Medium-High)

Sesuai PRD §8 & REPORT §7. Implementasi `Database.core.php` dulu, baru `Model.core.php`.

### 2.1 `system/Core/Database.core.php` (prerequisite)
- Wrapper `$wpdb` dengan API re-branded: `select()`, `insert()`, `update()`, `delete()`, `transaction()`
- Query builder minimal (opsional): `Database::table('posts')->where(...)->get()`
- Load via autoloader (namespace `System\Core\Database`)

### 2.2 `system/Core/Model.core.php`
- Abstract base: `abstract class Model`
- Method: `module()`, `db()` (return Database instance), `find()`, `findAll()`, `create()`, `update()`, `delete()`
- Table name bisa di-set via protected static `$table = 'wp_posts'`

### 2.3 Sample Module Models
- `modules/Dashboard/Models/Stats.model.php` — extend Model, contoh pakai Database
- `modules/Api/Models/Posts.model.php` — contoh query wp_posts

---

## 3. Fase 3: Security Polish (Prio: Medium)

| No | Item | Detail |
|---|------|--------|
| S1 | `Login.controller.php` password sanitize | Pakai `filter_input(INPUT_POST,'password',FILTER_UNSAFE_RAW)` eksplisit |
| S2 | `login.view.php` `$_POST['username']` → pass via view data | Jangan baca superglobal langsung di view |
| S3 | `login.view.php` action eksplisit | `action="<?php echo esc_url(...)"` |
| S4 | `*.bak` / `*.junk` di `.gitignore` — pastikan konsisten | Sudah di-.gitignore (poin 10 commit sebelumnya) |

---

## 4. Fase 4: Configuration & Assets

| No | Item | Detail |
|---|------|--------|
| C1 | Buat `system/Config/app.conf.php` | Konfigurasi app (name, debug, routing skip_paths) |
| C2 | Update `config()` helper | Sudah ada di `config.helper.php` — tapi perlu file config aktif |
| C3 | Enqueue font `Inter` (jika tetap dipakai) | `radykfw_asset('inter-font', 'css/fonts/inter.css')` |

---

## 5. Fase 5: Testing Infrastructure (Prio: Low-Medium)

| No | Item | Detail |
|---|------|--------|
| T1 | Buat struktur `tests/` minimal | PHPUnit + bootstrap |
| T2 | Hapus dev-deps dari `composer.json` jika tests belum siap | Atau implementasikan |
| T3 | `.gitignore` scope `vendor/` | Hapus `wp-content/plugins/*/vendor/` (terlalu luas) |

---

## 6. Priority Matrix

| Priority | Fase | Estimasi Effort |
|----------|------|-----------------|
| 🔴 High | Fase 1 (Runner + Router cleanup) | 1–2 jam |
| 🟠 High | Fase 2 (Database + Model core) | 3–4 jam |
| 🟡 Medium | Fase 3 (Security polish) | 1 jam |
| 🟡 Medium | Fase 4 (Config & assets) | 2 jam |
| 🔵 Low | Fase 5 (Testing infra) | 2–3 jam |

---

## 7. Decisions Made (Already Implemented)

| Item | Status | Commit |
|------|--------|--------|
| Autoloader strip `Controller` only for `System\` | ✅ | `736ebb5` |
| `view()` module-only + `system_view()` for templates | ✅ | `736ebb5` |
| Rename `system/Views` → `system/Templates` | ✅ | `736ebb5` |
| Base controllers (Public/Private/Api) | ✅ | `736ebb5` |
| Helper split & cleanup | ✅ | `736ebb5` |
| Sample modules (Dashboard, Api) | ✅ | `736ebb5` |

---

*Dibuat berdasarkan audit REPORT.md dan PRD.md. Revision 1 — 2026-11-05.*
