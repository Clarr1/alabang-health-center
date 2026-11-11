<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('extends legacy alert and audit tables without replacing historical rows and preserves them on rollback', function () {
    $migration = require database_path('migrations/2026_11_09_102818_create_syndromic_surveillance_tables.php');
    $migration->down();
    Schema::create('surveillance_alerts', function (Blueprint $table): void {
        $table->id();
        $table->string('reference')->unique();
        $table->string('syndrome');
        $table->string('mode');
        $table->string('status')->default('Open');
        $table->json('snapshot');
        $table->unsignedInteger('peak_count');
        $table->timestamp('first_detected_at');
        $table->timestamp('last_qualifying_at');
        $table->text('review_notes')->nullable();
        $table->timestamps();
    });
    Schema::create('surveillance_events', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('actor_id')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
        $table->string('action');
        $table->string('subject_type');
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->text('details');
        $table->timestamps();
    });
    DB::table('surveillance_alerts')->insert([
        'reference' => 'SIMULATED-LEGACY-001', 'syndrome' => 'Historical prototype syndrome', 'mode' => 'synthetic', 'status' => 'Open',
        'snapshot' => '{"original":true}', 'peak_count' => 7, 'first_detected_at' => now(), 'last_qualifying_at' => now(),
    ]);
    DB::table('surveillance_events')->insert(['action' => 'SIMULATED original event', 'subject_type' => 'legacy', 'details' => 'Original details']);
    $migration->up();
    expect(Schema::hasColumn('surveillance_alerts', 'surveillance_result_id'))->toBeTrue()
        ->and(DB::table('surveillance_alerts')->sole()->snapshot)->toBe('{"original":true}')
        ->and(DB::table('surveillance_events')->sole()->details)->toBe('Original details');
    $migration->down();
    expect(Schema::hasTable('surveillance_alerts'))->toBeTrue()
        ->and(Schema::hasColumn('surveillance_alerts', 'surveillance_result_id'))->toBeFalse()
        ->and(DB::table('surveillance_alerts')->sole()->reference)->toBe('SIMULATED-LEGACY-001')
        ->and(DB::table('surveillance_events')->count())->toBe(1);
    $migration->up();
});
