<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Facturación SAR: avisos por serie (CAI), datos fiscales en la venta, orden → factura. */
    public function up(): void
    {
        Schema::table('document_series', function (Blueprint $table) {
            $table->unsignedInteger('range_alert_threshold')->default(50)->after('issue_deadline');
            $table->unsignedInteger('deadline_alert_days')->default(15)->after('range_alert_threshold');
            $table->index(['kind', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            // Snapshot de lo fiscal impreso: CAI, rango, fecha límite, datos de exonerado.
            $table->json('fiscal_snapshot')->nullable()->after('issuer_snapshot');
            $table->foreignId('source_sale_id')->nullable()->after('notes')->constrained('sales')->restrictOnDelete();
            $table->foreignId('converted_to_sale_id')->nullable()->after('source_sale_id')->constrained('sales')->restrictOnDelete();
            $table->index(['terms', 'status', 'customer_id']);
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('username'));
        Schema::table('business_settings', fn (Blueprint $t) => $t->dropColumn('email'));
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['terms', 'status', 'customer_id']);
            $table->dropConstrainedForeignId('converted_to_sale_id');
            $table->dropConstrainedForeignId('source_sale_id');
            $table->dropColumn('fiscal_snapshot');
        });
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropIndex(['kind', 'status']);
            $table->dropColumn(['range_alert_threshold', 'deadline_alert_days']);
        });
    }
};
