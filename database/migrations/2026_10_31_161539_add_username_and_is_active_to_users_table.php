<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 60)->nullable()->unique();
            $table->boolean('is_active')->default(true)->index();
        });

        DB::table('users')->orderBy('user_id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('user_id', $user->user_id)->update(['username' => 'user-'.$user->user_id]);
            }
        }, 'user_id');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['username', 'is_active']);
        });
    }
};
