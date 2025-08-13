<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Organization;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $org = Organization::create([
            'organization_name' => 'livefit',
            'address' => 'Chennai', // or any other fields you defined
            'website' => 'https://example.com',
            'description' => 'This is the main branch of our organization.',
            'logo' => 'logo.png', // assuming you have a logo file
        ]);

        // Now create the user using the generated organization ID
        User::factory()->create([
            'name' => fake()->name(),
            'email' => 'admin@gmail.com',
            'phone' => '8675293400',
            'email_verified_at' => now(),
            'role' => 'admin',
            'organization_id' => $org->id, // <- dynamic, guaranteed to exist
            'password' => Hash::make('password'),
        ]);
    }
}
