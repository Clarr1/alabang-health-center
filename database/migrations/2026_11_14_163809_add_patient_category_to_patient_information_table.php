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
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->string('patient_category', 30)->nullable()->index();
            $table->text('category_override_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->dropIndex(['patient_category']);
            $table->dropColumn(['patient_category', 'category_override_reason']);
        });
    }
};
