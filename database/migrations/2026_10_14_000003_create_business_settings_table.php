<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos del emisor (§1). Fila única; se copia a `sales.issuer_snapshot`
     * en cada venta para que los documentos viejos se rendericen igual (§3.4).
     */
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name');
            $table->string('legal_name')->nullable();
            $table->string('activity')->nullable();
            $table->string('rtn')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->json('print_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
