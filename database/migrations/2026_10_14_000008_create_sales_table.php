<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ventas / documentos (§6). Inmutables una vez emitidas (§3.3); nunca se borran,
     * solo se anulan (§3.1). Dinero en numeric(12,2), nunca float (§3.6).
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_series_id')->constrained('document_series')->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('full_number')->unique(); // ej. 'OP-100123'

            $table->timestampTz('issued_at');
            $table->date('issue_date');

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name_snapshot');
            $table->string('customer_rtn_snapshot')->nullable();
            $table->json('issuer_snapshot');

            $table->string('terms')->default('cash'); // cash | credit
            $table->date('due_date')->nullable();

            $table->decimal('subtotal_exempt', 12, 2)->default(0);
            $table->decimal('subtotal_exonerated', 12, 2)->default(0);
            $table->decimal('subtotal_g15', 12, 2)->default(0);
            $table->decimal('subtotal_g18', 12, 2)->default(0);
            $table->decimal('tax_15', 12, 2)->default(0);
            $table->decimal('tax_18', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('status')->default('issued'); // issued | voided
            $table->text('void_reason')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('print_count')->default(0);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Numeración correlativa única por serie (§3.2).
            $table->unique(['document_series_id', 'number']);
            // Índices de reportes/listados (§6).
            $table->index('issue_date');
            $table->index(['status', 'issue_date']);
            $table->index(['customer_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
