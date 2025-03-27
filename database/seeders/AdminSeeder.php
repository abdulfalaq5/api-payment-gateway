<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * php artisan db:seed AdminSeeder
     */
    public function run()
    {
        User::create([
            'name' => 'Admin',
            'username' => 'admin@mail.com',
            'email' => 'admin@mail.com',
            'password' => Hash::make('Qwer1234!')
        ]);
    }
} 