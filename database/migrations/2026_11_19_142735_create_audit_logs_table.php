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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name');
            $table->string('action', 10)->index();
            $table->string('module')->index();
            $table->string('record_type')->index();
            $table->string('record_id')->nullable()->index();
            $table->string('record_description');
            $table->text('changed_fields')->nullable();
            $table->text('previous_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
