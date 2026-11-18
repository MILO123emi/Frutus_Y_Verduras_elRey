<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles del sistema (§5): admin, seller, accountant (solo lectura y reportes).
     * El PIN de admin se usa para anular ventas (patrón Ayala, §7.0-0B).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('seller')->after('email');
            $table->string('pin_hash')->nullable()->after('password');
            $table->boolean('active')->default(true)->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'pin_hash', 'active']);
        });
    }
};
