<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_kontak', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT (Assuming it should be auto-increment based on standard practice, though not explicitly stated as auto_increment in the dump, it is a primary key)
            $table->integer('id', true); // true = autoIncrement

            // alamat_jalan text
            $table->text('alamat_jalan')->nullable();
            
            // desa_kelurahan varchar(100)
            $table->string('desa_kelurahan', 100)->nullable();
            
            // kecamatan varchar(100)
            $table->string('kecamatan', 100)->nullable();
            
            // kabupaten_kota varchar(100)
            $table->string('kabupaten_kota', 100)->nullable();
            
            // provinsi varchar(100)
            $table->string('provinsi', 100)->nullable();
            
            // telepon varchar(20)
            $table->string('telepon', 20)->nullable();
            
            // email_resmi varchar(100)
            $table->string('email_resmi', 100)->nullable();
            
            // peta_embed_code text
            $table->text('peta_embed_code')->nullable();
            
            // updated_at timestamp NULL
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_kontak');
    }
};