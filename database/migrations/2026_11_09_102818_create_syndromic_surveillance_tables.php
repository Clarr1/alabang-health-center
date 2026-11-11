<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyAlerts = Schema::hasTable('surveillance_alerts');
        $legacyEvents = Schema::hasTable('surveillance_events');
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->string('surveillance_residence', 30)->default('Unverified')->index();
            $table->timestamp('residence_verified_at')->nullable();
            $table->foreignId('residence_verified_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
        });
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->timestamp('encounter_at')->nullable()->index();
        });
        Schema::create('syndrome_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('syndrome_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('syndrome_category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('minimum_matches');
            $table->timestamp('applies_from');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->text('rationale');
            $table->timestamps();
            $table->unique(['syndrome_category_id', 'version']);
        });
        Schema::create('syndrome_rule_symptoms', function (Blueprint $table): void {
            $table->foreignId('syndrome_rule_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('symptom_id')->constrained()->restrictOnDelete();
            $table->string('symptom_name');
            $table->primary(['syndrome_rule_version_id', 'symptom_id'], 'syndrome_rule_symptoms_pk');
        });
        Schema::create('surveillance_classifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records', 'medical_record_id')->restrictOnDelete();
            $table->foreignId('syndrome_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('syndrome_rule_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->boolean('matches');
            $table->json('evidence');
            $table->timestamps();
            $table->unique(['medical_record_id', 'syndrome_category_id'], 'surveillance_classification_unique');
        });
        Schema::create('surveillance_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('values');
            $table->boolean('legacy_alerts')->default(false);
            $table->boolean('legacy_events')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamps();
        });
        DB::table('surveillance_settings')->insert(['id' => 1, 'values' => '{}', 'legacy_alerts' => $legacyAlerts, 'legacy_events' => $legacyEvents, 'created_at' => now(), 'updated_at' => now()]);
        if (! $legacyEvents) {
            Schema::create('surveillance_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
                $table->string('action');
                $table->string('subject_type', 40);
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->text('details');
                $table->timestamps();
            });
        }
        Schema::create('surveillance_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20);
            $table->foreignId('triggered_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('status', 20)->default('Running');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->json('settings');
            $table->unsignedInteger('eligible_cases')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('surveillance_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surveillance_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('syndrome_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('syndrome_rule_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('syndrome_name');
            $table->unsignedInteger('observed');
            $table->double('expected')->nullable();
            $table->double('probability')->nullable();
            $table->double('adjusted_probability')->nullable();
            $table->string('model', 40)->nullable();
            $table->string('outcome', 30);
            $table->text('reason');
            $table->json('calculation');
            $table->timestamps();
            $table->unique(['surveillance_run_id', 'syndrome_category_id'], 'surveillance_result_unique');
        });
        Schema::create('surveillance_result_cases', function (Blueprint $table): void {
            $table->foreignId('surveillance_result_id')->constrained()->restrictOnDelete();
            $table->foreignId('surveillance_classification_id')->constrained()->restrictOnDelete();
            $table->primary(['surveillance_result_id', 'surveillance_classification_id'], 'surveillance_result_cases_pk');
        });
        if (! $legacyAlerts) {
            Schema::create('surveillance_alerts', function (Blueprint $table): void {
                $table->id();
                $table->string('reference')->unique();
                $table->string('syndrome');
                $table->string('mode', 20);
                $table->string('active_key')->nullable()->unique();
                $table->string('status', 30)->default('New')->index();
                $table->json('snapshot');
                $table->text('case_keys')->nullable();
                $table->unsignedInteger('peak_count');
                $table->timestamp('first_detected_at');
                $table->timestamp('last_qualifying_at');
                $table->timestamp('below_since')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
                $table->text('review_notes')->nullable();
                $table->timestamps();
            });
        }
        Schema::table('surveillance_alerts', function (Blueprint $table): void {
            $table->foreignId('syndrome_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('surveillance_result_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->string('scope')->default('Barangay Alabang, Muntinlupa City');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamp('detected_at')->nullable()->index();
        });
        Schema::create('surveillance_alert_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surveillance_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->text('notes');
            $table->text('outcome')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $state = DB::table('surveillance_settings')->where('id', 1)->first();
        Schema::dropIfExists('surveillance_alert_reviews');
        if ($state?->legacy_alerts) {
            Schema::table('surveillance_alerts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('syndrome_category_id');
                $table->dropForeign(['surveillance_result_id']);
                $table->dropConstrainedForeignId('assigned_to');
                $table->dropUnique(['surveillance_result_id']);
                $table->dropColumn('surveillance_result_id');
                $table->dropUnique(['fingerprint']);
                $table->dropIndex(['detected_at']);
                $table->dropColumn(['fingerprint', 'scope', 'version', 'detected_at']);
            });
        } else {
            Schema::dropIfExists('surveillance_alerts');
        }
        if (! $state?->legacy_events) {
            Schema::dropIfExists('surveillance_events');
        }
        foreach (['surveillance_result_cases', 'surveillance_results', 'surveillance_runs', 'surveillance_settings', 'surveillance_classifications', 'syndrome_rule_symptoms', 'syndrome_rule_versions', 'syndrome_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropIndex(['encounter_at']);
            $table->dropColumn('encounter_at');
        });
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('residence_verified_by');
            $table->dropIndex(['surveillance_residence']);
            $table->dropColumn(['surveillance_residence', 'residence_verified_at']);
        });
    }
};
