<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos de cuenta del cliente que no son ventas: abonos y saldos anteriores
     * (deudas de antes del sistema). Nunca se borran: se anulan.
     */
    public function up(): void
    {
        Schema::create('account_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('type');                 // payment | opening_balance
            $table->date('movement_date');
            $table->decimal('amount', 12, 2);       // siempre positivo
            $table->string('method')->nullable();   // cash | transfer | check | other (abonos)
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active | voided
            $table->text('void_reason')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'movement_date']);
            $table->index(['type', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_movements');
    }
};
