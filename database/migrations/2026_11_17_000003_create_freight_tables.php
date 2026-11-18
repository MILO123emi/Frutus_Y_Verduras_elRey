<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fletes: motoristas, viajes fijos (plantillas semanales) y viajes. */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('vehicle')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('freight_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = lunes … 7 = domingo
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('destination');
            $table->string('vehicle')->nullable();
            $table->string('departure_time')->nullable(); // "05:00"
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('freights', function (Blueprint $table) {
            $table->id();
            $table->date('trip_date');
            $table->foreignId('freight_schedule_id')->nullable()->constrained('freight_schedules')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('destination');
            $table->string('purpose')->default('business'); // business | family | customer | moving | other
            $table->string('for_whom')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('vehicle')->nullable();
            $table->string('departure_time')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('charge_amount', 12, 2)->nullable();
            $table->boolean('charge_to_account')->default(false);
            $table->string('status')->default('scheduled'); // scheduled | done | cancelled
            $table->boolean('customized')->default(false);  // editado a mano: no regenerar
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['freight_schedule_id', 'trip_date']);
            $table->index('trip_date');
            $table->index(['customer_id', 'charge_to_account', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freights');
        Schema::dropIfExists('freight_schedules');
        Schema::dropIfExists('drivers');
    }
};
