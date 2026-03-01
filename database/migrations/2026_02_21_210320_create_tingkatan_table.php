<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tingkatan', function (Blueprint $table) {
            // Mengubah tipe data id menjadi integer dan menjadikannya auto increment
            $table->unsignedInteger('id', true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tingkatan', function (Blueprint $table) {
            // Rollback ke tipe string/uuid jika diperlukan (sesuaikan tipe awal)
            $table->uuid('id')->change();
        });
    }
};