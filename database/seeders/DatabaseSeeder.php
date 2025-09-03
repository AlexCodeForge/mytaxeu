<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();


        // Admin test user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'axldeth@gmail.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        // $this->call([
        //     JobStatusTestDataSeeder::class,
        // ]);
    }

}
