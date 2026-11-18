<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Series de documento (§6). `kind=order` es la ORDEN DE PEDIDO sin CAI de hoy;
     * los campos de CAI quedan listos para una serie fiscal futura (§3.5), sin reescribir.
     */
    public function up(): void
    {
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('code');                       // 'OP', 'TAL', (futuro) 'FAC'
            $table->string('title');                      // 'ORDEN DE PEDIDO'
            $table->string('kind')->default('order');     // order | invoice
            $table->string('origin')->default('system');  // system | paper_book
            $table->unsignedBigInteger('next_number');
            $table->unsignedBigInteger('number_from');
            $table->unsignedBigInteger('number_to')->nullable();
            $table->unsignedSmallInteger('pad_length')->default(6);

            // Solo kind=invoice (futuro); nulos para la orden de pedido.
            $table->string('cai')->nullable();
            $table->string('establishment')->nullable();
            $table->string('emission_point')->nullable();
            $table->string('doc_type_code')->nullable();
            $table->date('issue_deadline')->nullable();

            $table->string('status')->default('active');  // active | exhausted | expired | inactive
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
