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
        Schema::create('profil_sekolah', function (Blueprint $table) {
            $table->id(); 
            
            $table->string('nama_sekolah', 150)->nullable();
            
            $table->string('cadis', 100)->nullable()->after('nama_sekolah')->comment('Cabang Dinas Wilayah');
            $table->string('logo', 255)->nullable()->after('cadis')->comment('Path/Nama file logo sekolah');
            $table->string('logo_provinsi', 255)->nullable()->after('logo')->comment('Path/Nama file logo provinsi');

            $table->text('sejarah')->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->string('npsn', 20)->nullable();
            $table->string('akreditasi', 10)->nullable();
            
            $table->string('guru_staf_id', 10)->nullable();
            
            $table->text('sambutan_kepsek')->nullable()->comment('Teks sambutan Kepala Sekolah');
            
            $table->timestamps();

            $table->foreign('guru_staf_id')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profil_sekolah');
    }
};