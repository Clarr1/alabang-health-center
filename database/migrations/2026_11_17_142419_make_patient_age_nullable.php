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
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->integer('age')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('patient_information')->whereNull('age')->update(['age' => 0]);
        Schema::table('patient_information', function (Blueprint $table): void {
            $table->integer('age')->nullable(false)->change();
        });
    }
};
