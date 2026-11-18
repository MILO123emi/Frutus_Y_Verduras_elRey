<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de venta (§6). Cada línea guarda snapshots (descripción, unidad, tipo de
     * impuesto) para que el documento no cambie si luego cambia el catálogo (§3.4).
     * Sin borrado en cascada sobre sales/sale_items (§3.1).
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description_snapshot');
            $table->string('unit_snapshot')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('tax_type_snapshot')->default('exempt'); // exempt | g15 | g18
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
