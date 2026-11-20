<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Development accounts are permitted only in local or testing environments.');
        }

        foreach ([
            ['admin@gmail.com', 'Admin', 'Admin', 'admin'],
            ['staff@example.test', 'Demo Staff', 'Local Health Unit Staff', 'staff-demo'],
            ['doctor@example.test', 'Demo Doctor', 'Doctor', 'doctor-demo'],
        ] as [$email, $name, $role, $password]) {
            $user = User::firstOrNew(['email' => $email]);
            if ($user->exists) {
                if ($user->role !== $role || ! $user->is_active) {
                    throw new RuntimeException('Existing development account has an incompatible role or is inactive: '.$email);
                }

                continue;
            }

            $user->forceFill([
                'name' => $name, 'role' => $role, 'is_active' => true,
                'password' => Hash::make($password), 'email_verified_at' => now(),
            ])->save();
        }
    }
}
