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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->string('device_token')->nullable();
            $table->boolean('is_online')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
