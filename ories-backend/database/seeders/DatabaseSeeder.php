<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed the 6 EVSU Campuses
        $campuses = [
            'Tacloban (Main)',
            'Burauen',
            'Carigara',
            'Dulag',
            'Ormoc',
            'Tanauan',
        ];

        foreach ($campuses as $campusName) {
            Campus::firstOrCreate(['name' => $campusName]);
        }

        // Retrieve the Tacloban Main Campus to assign to our test users
        $mainCampus = Campus::where('name', 'Tacloban (Main)')->first();

        // 2. Seed the VP of ORIES (Top Tier)
        User::firstOrCreate(
            ['email' => 'vp@evsu.edu.ph'],
            [
                'name' => 'Mark Lawrence Lacdao',
                'password' => Hash::make('password123'),
                'role' => 'vp_ories',
                'campus_id' => $mainCampus->id, 
                'department_name' => 'Office of the Vice President',
            ]
        );

        // 3. Seed a Campus Director (Middle Tier)
        User::firstOrCreate(
            ['email' => 'director@evsu.edu.ph'],
            [
                'name' => 'Axyll Judd Picardal',
                'password' => Hash::make('password123'),
                'role' => 'director',
                'campus_id' => $mainCampus->id,
                'department_name' => 'Tacloban Campus ORIES',
            ]
        );

        // 4. Seed a College/Department Head (Base Tier)
        User::firstOrCreate(
            ['email' => 'head@evsu.edu.ph'],
            [
                'name' => 'Aldrin Rey Taberara',
                'password' => Hash::make('password123'),
                'role' => 'head',
                'campus_id' => $mainCampus->id,
                'department_name' => 'College of Engineering',
            ]
        );
    }
}