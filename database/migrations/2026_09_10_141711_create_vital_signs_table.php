<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id('vital_id');
            
            // Foreign Key to patient table
            $table->foreignId('patient_id')
                  ->constrained('patient_information', 'patient_id')
                  ->onDelete('cascade');

            // Foreign Key to users table (person who recorded the vital signs)
            $table->foreignId('recorded_by')
                  ->constrained('users', 'user_id')
                  ->onDelete('cascade');

            // Vital sign fields
            $table->string('blood_pressure')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->integer('pulse_rate')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->decimal('height', 5, 2)->nullable(); // e.g., in cm
            $table->decimal('weight', 5, 2)->nullable(); // e.g., in kg
            $table->decimal('bmi', 4, 1)->nullable();
            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
}; 