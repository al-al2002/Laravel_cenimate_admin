<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
            if (Schema::hasTable('movies')) {
                return;
            }

            DB::statement(<<<'SQL'
                CREATE TABLE movies (
                    id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
                    title text NOT NULL,
                    description text,
                    cast_members text[] DEFAULT ARRAY[]::text[],
                    genre text[] NOT NULL,
                    language text NOT NULL,
                    duration_minutes integer NOT NULL,
                    release_date date NOT NULL,
                    trailer_url text,
                    poster_url text,
                    country text DEFAULT 'Philippines',
                    rating text,
                    is_active boolean DEFAULT true,
                    created_at timestamp with time zone DEFAULT now(),
                    updated_at timestamp with time zone DEFAULT now(),
                    duration integer,
                    cast text
                );
            SQL);
    }

    public function down()
    {
           Schema::dropIfExists('movies');
    }
};
