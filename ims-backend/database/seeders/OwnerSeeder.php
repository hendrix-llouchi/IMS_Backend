<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'System Owner',
            'age' => 30,
            'phone_number' => '0000000000',
            'location' => 'Head Office',
            'emergency_contact' => 'N/A',
            'email' => 'owner@ims.com',
            'username' => 'owner',
            'password' => Hash::make('owner1234'),
            'role' => 'owner',
            'is_active' => true,
            'is_temporary_password' => false,
        ]);
    }
}