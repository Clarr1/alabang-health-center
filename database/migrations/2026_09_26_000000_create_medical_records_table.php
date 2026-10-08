<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id('medical_record_id');
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id');
            $table->foreignId('vital_signs_id')->constrained('vital_signs', 'vital_id');
            $table->foreignId('handled_by')->constrained('users', 'user_id');
            $table->text('diagnosis');
            $table->text('prescription')->nullable();
            $table->text('treatment')->nullable();
            $table->text('consultation_notes')->nullable();
            $table->date('consultation_date');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};