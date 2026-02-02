<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key_name', 128)->unique();
            $table->integer('attempts')->default(0);
            $table->dateTime('last_attempt');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('rate_limits');
    }
};
