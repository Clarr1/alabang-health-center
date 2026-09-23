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
        Schema::create('patient_information', function (Blueprint $table) {
            $table->id('patient_id');
            $table->string('qr_code');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthdate');
            $table->integer('age');
            $table->enum('sex', ['Male', 'Female']);
            $table->text('address');
            $table->string('contact_number')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->date('registration_date');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_information');
    }
};