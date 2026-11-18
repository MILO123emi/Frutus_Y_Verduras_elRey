<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La extensión `unaccent` permite buscar "Maria" y encontrar "María" (§5).
     * Solo aplica en PostgreSQL.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP EXTENSION IF EXISTS unaccent');
        }
    }
};
