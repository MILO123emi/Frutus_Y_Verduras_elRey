<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de productos (§6). `form_order` conserva el orden del formulario de
     * papel para el "modo planilla" (§7.0-0B). Todos `exempt` por defecto (§3.7).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('default_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('reference_price', 12, 2)->nullable();
            $table->string('tax_type')->default('exempt'); // exempt | g15 | g18
            $table->unsignedInteger('form_order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('form_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
