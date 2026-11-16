<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table): void {
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('waistline', 5, 2)->nullable();
            $table->string('nutritional_status')->nullable();
            $table->string('nutrition_assessment_method')->nullable();
        });
        Schema::table('medical_records', function (Blueprint $table): void {
            foreach (['past_medical_history', 'family_medical_history', 'past_surgical_history', 'other_history', 'subjective_notes', 'other_pe_findings', 'laboratory_results', 'ancillary_results', 'diagnostic_findings', 'assessment_notes', 'referrals', 'follow_up_instructions'] as $field) {
                $table->text($field)->nullable();
            }
            $table->json('physical_examination')->nullable();
        });
        Schema::table('queues', function (Blueprint $table): void {
            $table->json('consultation_draft')->nullable();
            $table->timestamp('draft_saved_at')->nullable();
            $table->unsignedInteger('consultation_version')->default(0);
        });
        Schema::create('clinical_record_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id')->restrictOnDelete();
            $table->foreignId('queue_id')->nullable()->constrained('queues', 'queue_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('action');
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_record_audits');
        Schema::table('queues', function (Blueprint $table): void {
            $table->dropColumn(['consultation_draft', 'draft_saved_at', 'consultation_version']);
        });
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropColumn(['past_medical_history', 'family_medical_history', 'past_surgical_history', 'other_history', 'subjective_notes', 'other_pe_findings', 'laboratory_results', 'ancillary_results', 'diagnostic_findings', 'assessment_notes', 'referrals', 'follow_up_instructions', 'physical_examination']);
        });
        Schema::table('vital_signs', function (Blueprint $table): void {
            $table->dropColumn(['oxygen_saturation', 'waistline', 'nutritional_status', 'nutrition_assessment_method']);
        });
    }
};
