<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create indexes for better query performance (without CONCURRENTLY to avoid transaction issues)
        DB::statement('CREATE INDEX IF NOT EXISTS movies_is_active_index ON movies (is_active)');
        DB::statement('CREATE INDEX IF NOT EXISTS movies_created_at_index ON movies (created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS movies_is_active_index');
        DB::statement('DROP INDEX IF EXISTS movies_created_at_index');
    }
};
