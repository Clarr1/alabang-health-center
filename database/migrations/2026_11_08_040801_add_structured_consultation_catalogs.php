<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('review_status', 20)->default('approved')->index();
            $table->timestamps();
        });
        Schema::create('symptoms', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->foreignId('diagnosis_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->json('custom_symptoms')->nullable();
            $table->text('laboratory_requests')->nullable();
        });
        Schema::create('medical_record_symptoms', function (Blueprint $table): void {
            $table->foreignId('medical_record_id')->constrained('medical_records', 'medical_record_id')->restrictOnDelete();
            $table->foreignId('symptom_id')->constrained()->restrictOnDelete();
            $table->string('symptom_name');
            $table->primary(['medical_record_id', 'symptom_id']);
        });
        foreach (['Fever', 'Cough', 'Sore Throat', 'Headache', 'Diarrhea', 'Vomiting', 'Rash', 'Body Pain'] as $name) {
            DB::table('symptoms')->insert([
                'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_record_symptoms');
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diagnosis_id');
            $table->dropColumn(['chief_complaint', 'custom_symptoms', 'laboratory_requests']);
        });
        Schema::dropIfExists('symptoms');
        Schema::dropIfExists('diagnoses');
    }
};
