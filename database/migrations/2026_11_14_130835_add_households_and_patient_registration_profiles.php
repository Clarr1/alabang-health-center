<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('family_number')->unique();
            $table->string('label')->index();
            $table->text('address');
            $table->unsignedSmallInteger('family_count')->nullable();
            $table->decimal('residency_years', 6, 2)->nullable();
            $table->text('previous_address')->nullable();
            foreach (['home_ownership', 'water_source', 'water_source_other', 'drinking_water', 'drinking_water_other', 'toilet_facility', 'garbage_disposal', 'income_source', 'income_source_other', 'multi_income'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('average_income', 14, 2)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->foreignId('household_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('household_relationship')->nullable();
        });
        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patient_information', 'patient_id')->restrictOnDelete();
            foreach (['civil_status', 'birthplace', 'education', 'education_other', 'religion', 'email', 'landline', 'mother_name', 'philhealth_number', 'four_ps', 'four_ps_number', 'pwd', 'pwd_number', 'senior_number', 'vaccination_status', 'nutrition_status', 'smoking', 'vaping', 'vaping_duration', 'alcohol', 'sexually_active', 'reproductive_applicable', 'pregnant', 'family_planning', 'cervical_screening', 'hpv_vaccine', 'tetanus_vaccine', 'menarche', 'tuberculosis', 'tb_treatment', 'animal_bite', 'rabies_vaccine', 'dengue_vaccine', 'delivery_manner', 'delivery_location', 'preparer_designation'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['development_assessment', 'headss_home', 'headss_education', 'headss_activities', 'headss_drugs', 'headss_sexuality', 'headss_depression', 'headss_safety', 'headss_others', 'pertinent_information', 'ncd_notes', 'maternal_status'] as $field) {
                $table->text($field)->nullable();
            }
            foreach (['lmp', 'edc', 'cervical_date', 'hpv_date', 'tetanus_date'] as $field) {
                $table->date($field)->nullable();
            }
            foreach (['packs_per_year', 'bottles_per_year'] as $field) {
                $table->decimal($field, 10, 2)->nullable();
            }
            $table->unsignedSmallInteger('gravida')->nullable();
            $table->unsignedSmallInteger('para')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('patient_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id')->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('Reported');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'ended_at']);
        });
        Schema::create('immunization_doses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id')->restrictOnDelete();
            $table->string('vaccine_name');
            $table->string('schedule');
            $table->string('dose');
            $table->string('status');
            $table->date('administered_at')->nullable();
            $table->string('administered_by')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('immunization_doses')->restrictOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'vaccine_name']);
        });
        Schema::create('patient_profile_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patient_information', 'patient_id')->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('action')->index();
            $table->longText('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_profile_audits');
        Schema::dropIfExists('immunization_doses');
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('patient_profiles');
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('household_id');
            $table->dropColumn('household_relationship');
        });
        Schema::dropIfExists('households');
    }
};
