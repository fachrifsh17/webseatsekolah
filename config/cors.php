<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // 1. Pastikan jalur api dan storage masuk di sini agar gambar bisa di-crop
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // 2. Lebih baik spesifik ke localhost:3000 agar lebih aman
    'allowed_origins' => ['http://localhost:3000'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 3. SET KE TRUE! Ini wajib supaya session login (Sanctum) kamu tidak lepas-lepas
    'supports_credentials' => true,

];