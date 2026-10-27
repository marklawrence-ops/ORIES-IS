<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds campuses, colleges, and one account per user tier.
     * All seeded accounts use ORIES_SEED_PASSWORD (password123 unless set; always set it on a public server).
     */
    public function run(): void
    {
        $campuses = [
            ['Tacloban (Main)', 'TAC', [
                ['College of Engineering', 'COE'],
                ['College of Architecture and Allied Discipline', 'CAAD'],
                ['College of Arts and Sciences', 'CAS'],
                ['College of Business and Entrepreneurship', 'COBE'],
                ['College of Education', 'COED'],
                ['College of Technology', 'COT'],
            ]],
            ['Burauen', 'BUR', [['Department of Agriculture', 'AGRI'], ['Department of Education', 'EDUC']]],
            ['Carigara', 'CAR', [['Department of Information Technology', 'IT'], ['Department of Education', 'EDUC']]],
            ['Dulag', 'DUL', [['Department of Fisheries', 'FISH'], ['Department of Business', 'BUS']]],
            ['Ormoc', 'ORM', [['Department of Engineering', 'ENGG'], ['Department of Industrial Technology', 'INDT']]],
            ['Tanauan', 'TAN', [['Department of Engineering', 'ENGG'], ['Department of Hospitality Management', 'HM']]],
        ];

        foreach ($campuses as [$name, $code, $units]) {
            $campus = Campus::updateOrCreate(['name' => $name], ['code' => $code]);
            foreach ($units as [$unitName, $unitCode]) {
                Unit::updateOrCreate(['campus_id' => $campus->id, 'name' => $unitName], ['code' => $unitCode, 'type' => 'college']);
            }
        }

        $main = Campus::where('code', 'TAC')->first();
        $coe = Unit::where('campus_id', $main->id)->where('code', 'COE')->first();

        $accounts = [
            ['oup@evsu.edu.ph', 'Office of the University President', 'oup', null, 'University President', null],
            ['vp@evsu.edu.ph', 'Dr. Ramon S. Villanueva', 'vp', null, 'Vice President for Research, Innovation, and Extension Services', null],
            ['director.research@evsu.edu.ph', 'Dr. Liza M. Cabrera', 'director', 'research', 'Director for Research', null],
            ['director.innovation@evsu.edu.ph', 'Engr. Paolo D. Santiago', 'director', 'innovation', 'Director for Innovation', null],
            ['director.extension@evsu.edu.ph', 'Dr. Teresa A. Mendoza', 'director', 'extension', 'Director for Extension Services', null],
            ['staff.research@evsu.edu.ph', 'Joanna R. Dela Peña', 'staff', 'research', 'Research Office Staff', null],
            ['staff.innovation@evsu.edu.ph', 'Kevin L. Abarca', 'staff', 'innovation', 'Innovation Office Staff', null],
            ['staff.extension@evsu.edu.ph', 'Maricel T. Obando', 'staff', 'extension', 'Extension Office Staff', null],
            ['admin@evsu.edu.ph', 'ORIES Technical Staff', 'staff', null, 'Technical Staff / System Administrator', null],
            ['head@evsu.edu.ph', 'Engr. Victor P. Lagunzad', 'head', null, 'Dean, College of Engineering', $coe->id],
            ['faculty@evsu.edu.ph', 'Engr. Andrea C. Bautista', 'faculty', null, 'Instructor I', $coe->id],
        ];

        foreach ($accounts as [$email, $name, $role, $office, $position, $unitId]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => config('ories.seed_password'),
                'role' => $role,
                'office' => $office,
                'position' => $position,
                'employee_type' => in_array($role, ['faculty', 'head'], true) ? 'teaching' : null,
                'campus_id' => $main->id,
                'unit_id' => $unitId,
                'status' => 'active',
            ]);
        }

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
