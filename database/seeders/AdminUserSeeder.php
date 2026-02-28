<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@glamo.local';
        $phone = '255700000001';
        $now = now();

        $existingByEmail = DB::table('users')->where('email', $email)->first();

        if ($existingByEmail) {
            DB::table('users')
                ->where('id', $existingByEmail->id)
                ->update([
                    'name' => 'Admin',
                    'phone' => $phone,
                    'role' => 'admin',
                    'password' => Hash::make('password'),
                    'otp_verified_at' => $now,
                    'updated_at' => $now,
                ]);

            return;
        }

        $existingByPhone = DB::table('users')->where('phone', $phone)->first();

        if ($existingByPhone) {
            DB::table('users')
                ->where('id', $existingByPhone->id)
                ->update([
                    'name' => 'Admin',
                    'email' => $email,
                    'role' => 'admin',
                    'password' => Hash::make('password'),
                    'otp_verified_at' => $now,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => $email,
            'phone' => $phone,
            'role' => 'admin',
            'password' => Hash::make('password'),
            'otp_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

