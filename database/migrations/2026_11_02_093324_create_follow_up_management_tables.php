<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->boolean('sms_consent')->default(false);
        });
        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patient_information', 'patient_id');
            $table->foreignId('medical_record_id')->nullable()->unique()->constrained('medical_records', 'medical_record_id');
            $table->dateTime('scheduled_at')->index();
            $table->string('purpose', 255);
            $table->text('internal_notes')->nullable();
            $table->string('mobile', 20);
            $table->string('status')->default('Scheduled')->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('notification_version')->default(1);
            $table->string('active_slot')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamps();
        });
        Schema::create('follow_up_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follow_up_id')->constrained();
            $table->unsignedInteger('version');
            $table->string('type');
            $table->string('recipient', 20);
            $table->text('body');
            $table->string('status')->default('Pending')->index();
            $table->dateTime('due_at')->index();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('batch_id')->nullable();
            $table->string('device_id')->nullable();
            $table->string('outcome')->nullable();
            $table->json('attempt_history')->nullable();
            $table->timestamps();
            $table->unique(['follow_up_id', 'version', 'type'], 'follow_up_message_once');
        });
        Schema::create('follow_up_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follow_up_id')->constrained();
            $table->foreignId('actor_id')->constrained('users', 'user_id');
            $table->string('action');
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_events');
        Schema::dropIfExists('follow_up_messages');
        Schema::dropIfExists('follow_ups');
        Schema::table('patient_information', fn (Blueprint $table) => $table->dropColumn('sms_consent'));
    }
};
