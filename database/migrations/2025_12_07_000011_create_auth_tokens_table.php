<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token_hash', 255)->index();
            $table->unsignedInteger('user_id');
            $table->dateTime('expires_at');
            $table->tinyInteger('revoked')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users_admin')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('auth_tokens');
    }
};
