<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        User::firstOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'id' => Str::uuid(),
            'full_name' => 'Admin',
            'role' => 'admin',
            'password' => Hash::make('admin123'), // Change to a strong password
        ]);
    }
}
