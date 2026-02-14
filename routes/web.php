<?php

use Illuminate\Support\Facades\Route;

// Biarkan ini sebagai penanda bahwa server API kamu aktif
Route::get('/', function () {
    return response()->json([
        'app' => 'API Sistem Sekolah',
        'status' => 'Online',
        'version' => '1.0.0'
    ]);
});