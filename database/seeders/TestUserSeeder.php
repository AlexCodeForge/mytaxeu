<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if test user already exists
        $existingUser = User::where('email', 'testuser@mytaxeu.com')->first();

        if ($existingUser) {
            $this->command->info('Test user already exists with ID: ' . $existingUser->id);
            return;
        }

        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@mytaxeu.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'credits' => 100,
            'is_admin' => false,
            'is_suspended' => false,
        ]);

        $this->command->info('✅ Test user created successfully!');
        $this->command->info('ID: ' . $user->id);
        $this->command->info('Email: testuser@mytaxeu.com');
        $this->command->info('Password: password');
    }
}

