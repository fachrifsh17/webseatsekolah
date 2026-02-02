<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sekolah:clear-temp {--force}', function () {
    $disk = config('filesystems.default', 'local');
    $dir = 'temp';

    if (!Storage::disk($disk)->exists($dir)) {
        $this->info("Direktori '{$dir}' tidak ditemukan.");
        return 0;
    }

    if (!$this->option('force') && !$this->confirm("Hapus direktori '{$dir}'?")) {
        $this->info('Dibatalkan.');
        return 0;
    }

    try {
        if (Storage::disk($disk)->deleteDirectory($dir)) {
            $this->info("Direktori '{$dir}' berhasil dihapus.");
            Log::info("sekolah:clear-temp success.");
        } else {
            $this->warn("Gagal menghapus direktori '{$dir}'.");
        }
    } catch (\Throwable $e) {
        $this->error("Error: " . $e->getMessage());
        Log::error("sekolah:clear-temp error: " . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Hapus file sementara aplikasi');