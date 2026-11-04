# REPORT — Audit & Review RadykFW

> Audit menyeluruh terhadap plugin WordPress HMVC **radykfw** (standalone, tanpa dependensi third-party).
> Tanggal: 2026-11-04. Cakupan: seluruh file aktif di `system/`, `modules/`, `.supports`, `.starter`, `radykfw.php`, `composer.json`, `.gitignore`.
>
> **Kondisi boot terverifikasi:** rangkaian `.starter → .supports → Boot → Runner → helpers` dieksekusi hingga sukses pada PHP 8.3.36 (harness terpisah, tanpa WP). Registry berisi 18 key, `view()` & `get_config()` ter-load. **Tidak ada fatal error saat boot.**

## 1. Ringkasan

| Skala | Jumlah | Contoh |
|---|---|---|
| 🔴 Critical | 3 | Validasi input segment, fallback Router, `extract()` di view |
| 🟠 Major | 10 | Dead code, skip-routing, konstan global, no-op `fallbackToWordPress`, dst. |
| 🟡 Minor | 9 | Docblock, casing, inline CSS, dst. |
| 🔵 Info | 5 | Observasi |

**File di-lock** (tidak diubah, hanya diaudit): `radykfw.php`, `.starter`, `.supports`, `system/Core/Boot.core.php`, `system/Core/Registry.core.php`.

---

## 2. Temuan per File

### 2.1 `system/Core/Router.core.php`

- 🔴 **C1 — Input segment tak tervalidasi → path traversal / class-name injection** (baris 33–49, 55, 96)
  Segment URL masuk ke FQCN hanya lewat `ucfirst(strtolower($seg))` — tanpa validasi karakter. Autoloader (`Boot.core.php:59`) membangun nama file dari segmen tersebut.
  **Verifikasi independen:** `..%2F` (decode `%2F` → `/`) lolos `ucfirst(strtolower)` dan lolos explode `\` autoloader → `$parts` dapat segmen yang menghasilkan path tak terduga → `interlace` menghasilkan file tak intended → **bukan** sekadar 404, melainkan potensi eksekusi file / error tak terduga. Ini **kritikal**.
  **Fix:** tolak segmen yang tidak match `/^[A-Za-z_][A-Za-z0-9_]*$/` sebelum membangun `$controllerClass`.
- 🔴 **C2 — Fallback `Index::index()` membuat URL tak dikenal selalu "matched"** (baris 67–78)
  Jika `/welcome/doesnotexist`, dispatch gagal → fallback `Index` → `Index::index()` → `return true` → Runner **kill WP** dan render home module. Efek: 404 tidak pernah muncul; route salah diam-diam menampilkan konten lain.
  **Fix:** hanya perlakukan `Index::index` sebagai sukses untuk case default (0/1/2 segmen); untuk segmen lebih (method tak dikenal) return `false` agar WP menangani.
- 🟠 **M — `dispatchFallback` mengabaikan `$params` + docblock menyesatkan** (baris 173–180)
  `dispatchFallback($class, $params)` tidak pernah pakai `$params`; docblock-nya bilang "Retry the SAME method" padahal retry ke `index`. **Fix:** hapus param tak terpakai atau retry method asli; perbaiki docblock.
- 🟠 **M — `fallbackToWordPress()` no-op & mati** (baris 220–224)
  Dipanggil sekali, badan kosong. `return false` di baris 82 sudah menyatakan fall-through. **Fix:** hapus.
- 🟠 **M — `strtolower` merusak camelCase** (baris 33–42)
  `userProfile` → `Userprofile` → lookup file case-sensitive di filesystem bisa gagal. Inkonsisten juga: `case 0/1/2` normalisasi, `default` (baris 49) tidak.
  **Fix:** validasi + normalisasi seragam (tanpa `strtolower`).
- 🟠 **M — case 0 hardcode `Welcome/Welcome/index`** (baris 24–29)
  Komentar bilang "load default route from config", tapi tidak ada config key. **Fix:** baca dari config atau hapus komentar.
- 🟡 **m — `method_exists` sebelum `ReflectionMethod` terlihat redundant tapi load-bearing** (baris 100–105)
  `new ReflectionMethod` di luar `try` — akan throw `ReflectionException` uncaught. Tetap, atau pindahkan ke dalam `try`.
- 🔵 **i — `tryDispatchNoRecurse` duplikat ~80% `tryDispatch`** (baris 190–213)
  Risiko drift. **Fix:** ekstrak helper `invokeMethod(ReflectionMethod, class, args)`.
- 🔵 **i — class `final` tapi tanpa private constructor** — masih bisa di-`new`. Opsional: tutup.

### 2.2 `system/Core/Runner.core.php`

- 🟠 **M1 — `initRegistry()` mati** (baris 41–46)
  Method kosong + implikasinya tercomment; tak pernah dipanggil (`Registry` di-load `Boot` via `call_process('Registry')`). **Fix:** hapus.
- 🟠 **M2 — `shouldSkipRouting` cek path yang tak akan muncul** (baris 156)
  `in_array($path, ['wp-login.php', 'wp-signup.php', 'wp-activate.php'])` tapi `$wp->request` berisi path *tanpa* `.php` (mis. `login`, `signup`). Cek ini tidak pernah true.
  **Verifikasi independen:** `wp-admin`/`wp-login` tidak lewat sini karena `is_admin()` & guard sudah skip, jadi bug ini **latent** — tidak merusak saat ini, tapi menyesatkan. **Fix:** sesuaikan nilai, atau hapus jika memang tak relevan.
- 🟠 **M3 — `add_action('template_redirect', fn(){exit;})` per request** (baris 125)
  Closure baru didaftarkan tiap kali route matched. Redundansi: view yang di-render via `view()` sudah `exit` sendiri di guard direct-access.
  **Fix:** hapus `add_action` ini; biarkan controller/view mengurus exit.
- 🟠 **M4 — `uninstall()` memanggil `flush_rewrite_rules()`** (baris 93)
  Saat uninstall, `$wp_rewrite` bisa belum diinisialisasi → potensi fatal. Rewrite rules tidak relevan setelah plugin dihapus. **Fix:** hapus.
- 🟡 **m5 — `activate()` `wp_mkdir_p` tanpa cek return** (baris 78)
  Gagal buat dir (permission) → activation lanjut diam-diam. **Fix:** cek return.
- 🟡 **m6 — `adminNotices()` concat versi tanpa escape** (baris 173,179)
  Risiko rendah (konstan core). **Fix:** `esc_html()`.
- 🔵 **i7 — `handleRouting(\WP $wp, array $extra_query_vars = [])`** — param 2 tak pernah diisi WP (`parse_request` hanya pass `&$wp`). **Fix:** hapus.
- 🔵 **i8 — `register_uninstall_hook` dengan `static::uninstall`** — bekerja, tapi tidak standar; pakai string callable `['System\Core\Runner','uninstall']` lebih jelas.
- 🔵 **i9 — priority `parse_request` = 10** — setara dengan core; urutannya registration order. **Fix:** dokumentasikan, atau 9 bila ingin selalu terpotong sebelum REST.
- 🔵 **i10 — method publik tanpa `@since`** — konsisten dengan file lain, opsional.

### 2.3 `system/Helpers/utilities.helper.php`

- 🔴 **C3 — `extract($data)` di `view()`** (baris 77)
  Variable injection: attacker bisa set `title`/sebarang key lewat `$data` dan mem-bypass variabel view. Membuat static analysis mustahil.
  **Fix:** pass data eksplisit ke view; atau `extract` hanya dengan daftar whitelist key.
- 🟠 **M — Duplikasi dengan `.supports`**
  `dd()` (baris 229) & `dump()` (baris 248) menduplikasi `__dump`/`__print` di `.supports`. `dd()` return `never` + `exit` hanya kalau `WP_DEBUG` — perilaku tidak konsisten.
  **Fix:** satu sumber saja (simpan helper di `.supports`, hapus di sini), atau hapus `dd`/`dump` jika tidak dipakai.
- 🟡 **m — `config()` pakai `static $config = null` global** (baris 125)
  Cache per-process — aman, tapi bila file config berubah saat runtime tak akan ke-load ulang. Catatan, bukan bug.
- 🔵 **i — helper global mem-pollute namespace global**
  `view()`, `config()` **tidak** bertabrakan dengan WP core (WP tidak punya keduanya), jadi aman saat ini. `dump()` berisiko: banyak polyfill/extension WP mendefinisikan `dump()` global.
  **Rekomendasi:** beri prefix `radykfw_` pada helper yang berpotensi tabrakan (`dump` → `radykfw_dump`), atau guard `if (!function_exists)`.

### 2.4 `modules/Login/Controllers/Login.controller.php`

- 🟠 **M — `include` bypass `view()`** (baris 95)
  `loginWithError` pakai `include RADYKFW_MODULES_DIR . 'Login/Views/login.view.php'` — lewat framework view system (output buffering + 404 fallback + data extract hilang). Inkonsisten dengan `login()` yang pakai `view()`.
  **Fix:** `view('modules/Login/Views/login.view', ['error' => $error]);`
- 🟠 **M — password tidak di-sanitize** (baris 52)
  `username` disanitasi, `$password` tidak. Untuk password, memang jangan di-sanitize (memutasi nilai) — tapi gunakan `filter_input(INPUT_POST,'password',FILTER_UNSAFE_RAW)` yang eksplisit.
- 🟡 **m — hardcode redirect `/dashboard`** (baris 72)
  Tak ada route `/dashboard` di codebase → 404 setelah login sukses.
- 🔵 **i — seluruh method static** — tidak bisa di-tes / DI. Konsisten dengan Welcome, tapi batasan arsitektur.
- 🔵 **i — `declare(strict_types)`** sudah ada di baris 1.

### 2.5 `modules/Welcome/Controllers/{Index,Welcome}.controller.php`

- 🟠 **M — `Welcome.controller.php` kemungkinan mati**
  `Index.controller` sudah handle `/welcome`, `/welcome/home`, dst via fallback Router. `Welcome` hanya terinvoke kalau URL eksplisit menyebut controller `Welcome`.
  **Verifikasi:** Router default case `method = $segments[2]` — untuk `/welcome/home/door` method=`door`, bukan `Welcome`. Jadi `Welcome` kelas **hampir tak pernah dipanggil**. Kandidat dead code — konfirmasi apakah memang dipakai.
- 🟡 **m — `...$args` tak terpakai di view** (`home.view.php`, `door.view.php` tidak akses `$args`).
- 🔵 **i — semua static.**

### 2.6 `modules/Welcome/Views/index.view.php`

- 🟡 **m — footer link hardcode** `/documentation`, `/github`, `/changelog` (baris 171–174) — tak ada route-nya.
- 🟡 **m — font `Inter` referenced tapi tak di-enqueue** → fallback sistem.

### 2.7 `modules/Login/Views/login.view.php`

- 🟡 **m — `$_POST['username']` di view** (baris 132) — baca superglobal di view. **Fix:** pass via view data.
- 🟡 **m — `action=""`** (baris 127) — andalkan browser; eksplisitkan `action`.
- 🔵 **i — nonce** sudah benar (`wp_nonce_field` + `wp_verify_nonce`). Bagus.

### 2.8 `composer.json`

- 🟠 **M — dev deps (`phpunit`, `php_codesniffer`, `phpstan`) tapi tak ada `tests/`**
  `composer test/cs/analyse` gagal.
  **Fix:** buat `tests/` minimal, atau hapus script/dev-deps sampai siap.
- 🔵 **i — `autoload.files` memuat `utilities.helper.php`** — helper global, bukan PSR-4; bekerja tapi tidak lazim.
- ✅ **i — Tidak ada runtime dependensi** — sesuai syarat "standalone". Bagus.

### 2.9 `.gitignore`

- 🟠 **M — pola `wp-content/plugins/*/vendor/` terlalu luas** (baris 52–53)
  Men-ignore `vendor/` di **semua** plugin, bukan hanya ini.
  **Fix:** sudah ada `vendor/` (baris 7) + `modules/*/vendor/` (baris 56) — hapus dua baris yang terlalu luas.
- 🟡 **m — missing:** `.supports`, `radykfw.php.old.junk`, `*.bak` (sebagian), `*.junk` (sebagian). `*.bak` & `*.junk` sebagian ada, pastikan konsisten.

### 2.10 `.supports` & `.starter` (di-lock, hanya catat)

- 🟡 **m — `is()` sebagai nama fungsi global** — `is` sangat umum di plugin lain; risiko tabrakan global.
  **Catatan:** karena di-lock, hanya direkomendasikan.
- 🔵 **i — `.starter` memakai `require` (bukan `require_once`) untuk `.supports`** — aman karena hanya di-load sekali di boot, tapi rapuh bila di-include ulang.

### 2.11 `system/Contracts/Dispatchable.contract.php`

- ✅ **Clean** — interface `Dispatchable` (sudah di-rename dari `DispatchableContract`), cocok dengan `Router implements Dispatchable`. Nama file == nama interface. Sesuai aturan.

### 2.12 `system/Core/Registry.core.php` (di-lock)

- ✅ **Clean** — single source of truth; `ext` method memakai default `system_dir` (konstan `.starter`) — acceptable karena `.starter` memang sumber nama direktori.
- 🔵 **i — `Registry::ext()` saat ini tidak punya konsumen aktif** (hanya di `.bak`/`.junk`). Dead API, tapi di-lock.

---

## 3. Prioritas Perbaikan (jika mulai eksekusi)

1. **Router C1 + C2** — validasi segmen & perbaiki fallback `Index`. Tanpa ini, plugin-nya tidak bisa disebut aman.
2. **`view()` extract** — whitelist key atau pass eksplisit.
3. **Runner M3 (exit redundan), M4 (uninstall), M1 (dead method)**.
4. **`utilities.helper` dedup `dd`/`dump`, prefix helper, `view` di-`include` di Login.**
5. **`.gitignore` scope `vendor/`, `composer.json` tanpa `tests/`.**

---

## 4. Yang Dipertahankan (sudah kelas dunia)

- Arsitektur Boot→Registry→autoloader→Runner→Router: deterministik, zero-dependency.
- `.starter` sebagai single source of truth untuk nama direktori.
- `Registry` sebagai single source of truth untuk path/namespace di runtime.
- Konvensi nama file == nama class (tanpa suffix) — konsisten.
- Helper config-driven (`helper.conf.php`) — extensible tanpa ubah kode.
- Nonce di Login view — benar.
- Standalone, tanpa dependensi runtime.

---

*Dibuat sebagai bagian dari deep review/audit. File di-lock tidak diubah; temuan di atas adalah rekomendasi.*
