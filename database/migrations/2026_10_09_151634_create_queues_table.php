<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_days', function (Blueprint $table) {
            $table->date('queue_date')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('queues', function (Blueprint $table) {
            $table->id('queue_id');
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id')->restrictOnDelete();
            $table->unsignedInteger('queue_number');
            $table->date('queue_date');
            $table->enum('status', ['Waiting', 'Called', 'Vital Signs', 'Ready for Consultation', 'In Consultation', 'Completed', 'Cancelled', 'No Show'])->default('Waiting');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('active_patient_id')->nullable()->virtualAs("CASE WHEN status IN ('Completed', 'Cancelled', 'No Show') THEN NULL ELSE patient_id END");
            $table->unique(['queue_date', 'queue_number']);
            $table->unique(['queue_date', 'active_patient_id']);
            $table->index(['queue_date', 'status']);
            $table->foreign('queue_date')->references('queue_date')->on('queue_days')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
        Schema::dropIfExists('queue_days');
    }
};
