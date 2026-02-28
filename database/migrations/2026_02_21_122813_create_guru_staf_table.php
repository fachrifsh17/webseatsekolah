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
        Schema::create('guru_staf', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('user_id', 10)->nullable();
            
            $table->string('nip', 18)->unique()->nullable();
            $table->string('nuptk', 16)->unique()->nullable();
            
            $table->string('nama', 100)->nullable();
            
            $table->string('no_hp', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('alamat_lengkap')->nullable();
            $table->string('jenis_kelamin', 15)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama', 20)->nullable();
            $table->string('pendidikan_terakhir', 50)->nullable();
            
            $table->string('jabatan_fungsional', 100)->nullable();
            $table->string('status_kepegawaian', 50)->nullable();
            $table->string('foto', 255)->nullable();
            
            $table->string('jurusan_id', 10)->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            $table->foreign('jurusan_id')->references('id')->on('jurusan')
                  ->onDelete('set null')->onUpdate('cascade');

            $table->foreign('user_id')->references('id')->on('users')
                  ->onDelete('set null')->onUpdate('cascade');

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_staf');
    }
};