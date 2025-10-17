<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Members;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleMembersSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create the demo organization
        $org = Organization::firstOrCreate(
            ['organization_name' => 'livefit'],
            [
                'address' => 'Chennai',
                'website' => 'https://example.com',
                'description' => 'Demo org for seeding members',
                'logo' => 'logo.png',
            ]
        );

        $samples = [
            ['name' => 'John Doe',    'email' => 'john.doe+seed@example.com',    'image' => 'members/sample1.jpg'],
            ['name' => 'Jane Smith',  'email' => 'jane.smith+seed@example.com',  'image' => 'members/sample2.jpg'],
            ['name' => 'Alex Johnson','email' => 'alex.johnson+seed@example.com','image' => 'members/sample3.jpg'],
        ];

        $i = 1;
        foreach ($samples as $s) {
            Members::firstOrCreate(
                ['email' => $s['email']],
                [
                    'organization_id' => $org->id,
                    'name' => $s['name'],
                    'register_no' => 'LIV' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'phone' => '90000000' . $i,
                    'address' => 'Chennai',
                    'plan' => ['basic','middle','prime'][$i % 3],
                    'age' => 20 + $i,
                    'status' => 'active',
                    'join_date' => now()->subDays($i),
                    'payment_status' => 'due',
                    'image' => $s['image'],
                ]
            );
            $i++;
        }
    }
}