<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes (§6). `default_terms` alimenta el selector Contado/Crédito en la venta.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rtn')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('default_terms')->default('cash'); // cash | credit
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->unsignedInteger('credit_days')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
