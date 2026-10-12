<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vital_signs', 'medical_records'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('queue_id')->nullable()->unique()->constrained('queues', 'queue_id')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['medical_records', 'vital_signs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('queue_id');
            });
        }
    }
};
