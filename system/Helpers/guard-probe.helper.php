<?php declare (strict_types=1);

/**
 * TEST: helper yang sengaja mengandung class — untuk memverifikasi
 * helper type guard di `Runner::initHooks()` + notice di `adminNotices()`.
 * File ini HARUSNYA tidak pernah di-require; jika admin notice muncul,
 * guard berfungsi. hapus file + entri config setelah verifikasi.
 */
if (!defined ('ABSPATH')) {exit;}

class RadykfwGuardProbe
{
    public function __construct () {}
}
