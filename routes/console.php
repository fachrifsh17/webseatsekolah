<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Di sini Anda dapat mendaftarkan command artisan berbasis closure.
| Cocok untuk tugas kecil atau testing.
|--------------------------------------------------------------------------
*/

Artisan::command('inspire:daily', function () {
    $this->comment(Inspiring::quote());
})->describe('Tampilkan quote inspiratif (contoh)');

/*
 * Hapus direktori temp aplikasi sekolah.
 * Opsi:
 *   --force    : langsung hapus tanpa konfirmasi
 */
Artisan::command('sekolah:clear-temp {--force : Hapus tanpa konfirmasi}', function () {
    $disk = config('filesystems.default', 'local');
    $dir = 'temp';

    // cek apakah direktori ada
    if (! Storage::disk($disk)->exists($dir)) {
        $this->info("Direktori '{$dir}' tidak ditemukan di disk '{$disk}'. Tidak ada yang dihapus.");
        return 0;
    }

    // jika tidak pakai --force, minta konfirmasi
    if (! $this->option('force')) {
        if (! $this->confirm("Anda akan menghapus direktori '{$dir}' pada disk '{$disk}'. Lanjutkan?")) {
            $this->info('Dibatalkan oleh pengguna.');
            return 0;
        }
    }

    try {
        $deleted = Storage::disk($disk)->deleteDirectory($dir);

        if ($deleted) {
            $this->info("Direktori '{$dir}' berhasil dihapus dari disk '{$disk}'.");
            Log::info("sekolah:clear-temp - directory '{$dir}' deleted on disk '{$disk}'.");
        } else {
            $this->warn("Perintah dijalankan tetapi direktori '{$dir}' mungkin kosong atau tidak dapat dihapus.");
            Log::warning("sekolah:clear-temp - deleteDirectory returned false for '{$dir}' on disk '{$disk}'.");
        }
    } catch (\Throwable $e) {
        $this->error("Terjadi kesalahan saat menghapus direktori: " . $e->getMessage());
        Log::error("sekolah:clear-temp error: " . $e->getMessage(), ['exception' => $e]);
        return 1;
    }

    return 0;
})->describe('Hapus file sementara aplikasi sekolah (gunakan --force untuk tanpa konfirmasi)');
