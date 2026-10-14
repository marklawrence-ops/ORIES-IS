<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Certificate;
use App\Models\Project;
use App\Models\Target;
use App\Models\Unit;
use App\Models\User;
use App\Services\MovStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Fictional demonstration records covering every stage, outcome type, and verification
 * state so dashboards, reports, and certificates can be exercised end to end.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Project::exists()) {
            return;
        }

        $unit = fn (string $campus, string $code) => Unit::where('code', $code)
            ->whereHas('campus', fn ($q) => $q->where('code', $campus))->first();

        $people = [
            ['faculty@evsu.edu.ph', null, null, null],
            ['head@evsu.edu.ph', null, null, null],
            ['m.ramos@evsu.edu.ph', 'Dr. Marlon G. Ramos', 'TAC', 'COE'],
            ['j.villareal@evsu.edu.ph', 'Engr. Janine B. Villareal', 'TAC', 'COE'],
            ['r.espina@evsu.edu.ph', 'Prof. Rodel F. Espina', 'TAC', 'CAS'],
            ['c.gabriel@evsu.edu.ph', 'Dr. Cristina V. Gabriel', 'TAC', 'CAS'],
            ['a.tan@evsu.edu.ph', 'Arch. Albert S. Tan', 'TAC', 'CAAD'],
            ['l.morales@evsu.edu.ph', 'Dr. Lorna P. Morales', 'TAC', 'COBE'],
            ['e.cruz@evsu.edu.ph', 'Prof. Edwin H. Cruz', 'TAC', 'COED'],
            ['n.ferrer@evsu.edu.ph', 'Engr. Nestor J. Ferrer', 'TAC', 'COT'],
            ['g.abella@evsu.edu.ph', 'Dr. Grace M. Abella', 'BUR', 'AGRI'],
            ['d.salazar@evsu.edu.ph', 'Prof. Dennis R. Salazar', 'CAR', 'IT'],
            ['h.ortega@evsu.edu.ph', 'Dr. Helen C. Ortega', 'DUL', 'FISH'],
            ['b.navarro@evsu.edu.ph', 'Engr. Benjie A. Navarro', 'ORM', 'ENGG'],
            ['s.lim@evsu.edu.ph', 'Ms. Sheila D. Lim', 'TAN', 'HM'],
            ['p.reyes@evsu.edu.ph', 'Mr. Patrick O. Reyes', 'TAC', 'COT'],
        ];

        $users = [];
        foreach ($people as [$email, $name, $campus, $code]) {
            if ($name === null) {
                $users[$email] = User::where('email', $email)->firstOrFail();

                continue;
            }
            $u = $unit($campus, $code);
            $users[$email] = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password123',
                'role' => 'faculty',
                'employee_type' => $email === 'p.reyes@evsu.edu.ph' ? 'non_teaching' : 'teaching',
                'position' => $email === 'p.reyes@evsu.edu.ph' ? 'Laboratory Technician' : 'Assistant Professor',
                'campus_id' => $u->campus_id,
                'unit_id' => $u->id,
                'status' => 'active',
            ]);
        }

        User::updateOrCreate(['email' => 'k.delosreyes@evsu.edu.ph'], [
            'name' => 'Ms. Karen B. Delos Reyes',
            'password' => 'password123',
            'role' => 'faculty',
            'employee_type' => 'teaching',
            'position' => 'Instructor I',
            'campus_id' => Campus::where('code', 'CAR')->value('id'),
            'unit_id' => $unit('CAR', 'EDUC')->id,
            'status' => 'pending',
        ]);

        $staff = fn (string $office) => User::where('role', 'staff')->where('office', $office)->first();
        $director = fn (string $office) => User::where('role', 'director')->where('office', $office)->first();
        $year = CarbonImmutable::now()->year;
        $d = fn (int $yearsAgo, int $month, int $day = 15) => CarbonImmutable::create($year - $yearsAgo, $month, $day)->toDateString();

        // [title, office, lead, members, stage, proposed, started, completed, funding, budget, outcomes, partner tag]
        $records = [
            ['Smart Water Quality Monitoring System for Aquaculture Ponds in Dulag, Leyte', 'research', 'h.ortega@evsu.edu.ph', ['n.ferrer@evsu.edu.ph'], 'completed', $d(3, 2), $d(3, 6), $d(2, 11), 'CHED-GIA', 850000,
                [['publication', 'IoT-Based Water Quality Monitoring for Tilapia Ponds', 'Journal of Aquaculture Engineering', 'scopus', $d(1, 4), 6], ['technology_transfer', 'Licensing of pond monitoring kit to Dulag fisherfolk cooperative', 'Dulag Fisherfolk Association', null, $d(1, 9), null]], 'BFAR Region VIII'],
            ['Development of a Low-Cost Seismic Retrofitting Technique for Public School Buildings', 'research', 'm.ramos@evsu.edu.ph', ['j.villareal@evsu.edu.ph', 'faculty@evsu.edu.ph'], 'completed', $d(4, 1), $d(4, 5), $d(2, 3), 'DOST-PCIEERD', 1200000,
                [['publication', 'Cost-Effective Seismic Retrofitting of Reinforced Concrete School Buildings', 'Philippine Engineering Journal', 'aci', $d(2, 8), 3], ['policy', 'Adopted in DepEd Region VIII school building maintenance guidelines', 'DepEd Regional Office VIII', null, $d(1, 2), null]], 'DepEd Region VIII'],
            ['Calamansi-Based Functional Juice Beverage with Extended Shelf Life', 'innovation', 'g.abella@evsu.edu.ph', ['l.morales@evsu.edu.ph'], 'completed', $d(3, 3), $d(3, 7), $d(1, 6), 'DA-BAR', 650000,
                [['commercialization', 'Smart Juice calamansi beverage launched with local MSME partner', 'Burauen Women Producers Cooperative', null, $d(0, 3), null], ['technology_transfer', 'Technology transfer agreement for juice processing', 'Burauen Women Producers Cooperative', null, $d(1, 10), null]], 'DTI Leyte'],
            ['Flood Early Warning Mobile Application for Barangays Along the Binahaan River', 'research', 'd.salazar@evsu.edu.ph', [], 'ongoing', $d(1, 2), $d(1, 6), null, 'EVSU Internal Research Fund', 300000, [], 'Pastrana LGU'],
            ['Assessment of Microplastic Contamination in Cancabato Bay', 'research', 'c.gabriel@evsu.edu.ph', ['r.espina@evsu.edu.ph'], 'ongoing', $d(1, 1), $d(1, 4), null, 'DOST-PCAARRD', 900000, [], null],
            ['Machine Learning Model for Predicting Student Attrition in State Universities', 'research', 'faculty@evsu.edu.ph', ['head@evsu.edu.ph'], 'ongoing', $d(0, 1), $d(0, 3), null, 'EVSU Internal Research Fund', 150000, [], null],
            ['Thermal Performance of Coconut Coir Composite Panels for Tropical Housing', 'research', 'a.tan@evsu.edu.ph', [], 'completed', $d(2, 2), $d(2, 5), $d(1, 12), 'EVSU Internal Research Fund', 250000,
                [['publication', 'Thermal Conductivity of Coconut Coir Composite Wall Panels', 'Journal of Building Engineering', 'scopus', $d(0, 2), 2], ['presentation', 'Coir Composite Panels for Resilient Housing', 'International Conference on Sustainable Architecture', null, $d(1, 11), null]], null],
            ['Financial Literacy Program for Market Vendors in Tacloban City', 'extension', 'l.morales@evsu.edu.ph', ['e.cruz@evsu.edu.ph'], 'completed', $d(2, 1), $d(2, 3), $d(2, 12), 'EVSU Extension Fund', 120000, [], 'Tacloban City Government'],
            ['Basic Computer Literacy Training for Senior Citizens of Carigara', 'extension', 'd.salazar@evsu.edu.ph', [], 'completed', $d(1, 1), $d(1, 2), $d(1, 5), 'EVSU Extension Fund', 80000, [], 'Carigara OSCA'],
            ['Community-Based Mangrove Rehabilitation and Livelihood Program', 'extension', 'h.ortega@evsu.edu.ph', [], 'ongoing', $d(1, 5), $d(1, 8), null, 'DENR', 500000, [], 'DENR CENRO Tacloban'],
            ['Food Safety and Sanitation Training for Tanauan Food Vendors', 'extension', 's.lim@evsu.edu.ph', [], 'ongoing', $d(0, 2), $d(0, 4), null, 'EVSU Extension Fund', 60000, [], 'Tanauan MHO'],
            ['Solar-Powered Cold Storage Unit for Small-Scale Fishers', 'innovation', 'b.navarro@evsu.edu.ph', ['n.ferrer@evsu.edu.ph', 'p.reyes@evsu.edu.ph'], 'ongoing', $d(1, 3), $d(1, 9), null, 'DOST-TAPI', 780000, [], null],
            ['Automated Hydroponics Controller Using Microcontrollers', 'innovation', 'n.ferrer@evsu.edu.ph', ['p.reyes@evsu.edu.ph'], 'completed', $d(2, 4), $d(2, 6), $d(1, 8), 'EVSU Internal Research Fund', 180000,
                [['commercialization', 'Hydroponics controller kits sold through EVSU incubation hub', 'EVSU Technology Business Incubator', null, $d(0, 5), null]], null],
            ['Teaching Strategies for Mother Tongue-Based Multilingual Education in Leyte', 'research', 'e.cruz@evsu.edu.ph', [], 'completed', $d(3, 1), $d(3, 3), $d(2, 1), 'EVSU Internal Research Fund', 90000,
                [['publication', 'Teacher Practices in MTB-MLE Classrooms of Eastern Visayas', 'Asia Pacific Journal of Education Research', 'other', $d(2, 7), 1]], null],
            ['Design of a Bamboo-Reinforced Pedestrian Footbridge', 'research', 'j.villareal@evsu.edu.ph', ['m.ramos@evsu.edu.ph'], 'proposal', $d(0, 6), null, null, 'DPWH', 400000, [], null],
            ['Livelihood Training on Abaca Handicraft Production for Burauen Women', 'extension', 'g.abella@evsu.edu.ph', [], 'proposal', $d(0, 7), null, null, 'EVSU Extension Fund', 75000, [], 'Burauen MSWDO'],
            ['Development of a Rice Straw Biochar Production Kiln', 'innovation', 'm.ramos@evsu.edu.ph', [], 'terminated', $d(2, 3), $d(2, 6), null, 'EVSU Internal Research Fund', 200000, [], null],
            ['Inventory of Endemic Medicinal Plants in Leyte Highlands', 'research', 'r.espina@evsu.edu.ph', [], 'completed', $d(5, 2), $d(5, 6), $d(4, 10), 'CHED-GIA', 350000,
                [['publication', 'Ethnobotanical Survey of Medicinal Plants in the Leyte Highlands', 'Philippine Journal of Science', 'scopus', $d(3, 5), 14]], null],
        ];

        foreach ($records as [$title, $office, $leadEmail, $memberEmails, $stage, $proposed, $started, $completed, $funding, $budget, $outcomes, $partner]) {
            $lead = $users[$leadEmail];
            $project = Project::create([
                'title' => $title,
                'office' => $office,
                'abstract' => 'Fictional demonstration record for the ORIES-IS: '.$title.'.',
                'keywords' => $office,
                'lead_id' => $lead->id,
                'campus_id' => $lead->campus_id,
                'unit_id' => $lead->unit_id,
                'stage' => $stage,
                'verification_status' => 'verified',
                'funding_source' => $funding,
                'budget' => $budget,
                'proposed_at' => $proposed,
                'start_date' => $started,
                'completed_at' => $completed,
                'end_date' => $completed,
                'last_verified_at' => $completed ?? $started ?? $proposed,
            ]);

            $project->members()->create(['user_id' => $lead->id, 'name' => $lead->name, 'role' => 'lead']);
            foreach ($memberEmails as $email) {
                $project->members()->create(['user_id' => $users[$email]->id, 'name' => $users[$email]->name, 'role' => 'member']);
            }
            $project->tags()->create(['type' => 'unit', 'unit_id' => $lead->unit_id, 'label' => $lead->unit->name]);
            if ($partner) {
                $project->tags()->create(['type' => 'partner', 'label' => $partner]);
            }

            $verifier = $staff($office);
            $history = [['proposal', null, null, $proposed]];
            if ($started) {
                $history[] = ['stage', 'proposal', 'ongoing', $started];
            }
            if ($completed) {
                $history[] = ['stage', 'ongoing', 'completed', $completed];
            }
            if ($stage === 'terminated') {
                $history[] = ['stage', 'ongoing', 'terminated', $d(1, 2)];
            }
            foreach ($history as [$type, $from, $to, $date]) {
                $project->updates()->forceCreate([
                    'submitted_by' => $lead->id, 'type' => $type, 'from_stage' => $from, 'to_stage' => $to,
                    'data' => $type === 'stage' ? ['date' => $date] : null, 'status' => 'verified',
                    'reviewed_by' => $verifier->id, 'reviewed_at' => $date, 'created_at' => $date, 'updated_at' => $date,
                ]);
            }

            foreach ($outcomes as [$type, $outTitle, $venue, $indexing, $date, $citations]) {
                $project->outcomes()->create(compact('type', 'venue', 'indexing', 'date', 'citations') + ['title' => $outTitle]);
                $project->updates()->forceCreate([
                    'submitted_by' => $lead->id, 'type' => 'outcome', 'status' => 'verified', 'reviewed_by' => $verifier->id,
                    'reviewed_at' => $date, 'created_at' => $date, 'updated_at' => $date,
                    'data' => compact('type', 'venue', 'indexing', 'date', 'citations') + ['title' => $outTitle],
                ]);
            }

            ActivityLog::record($project, $verifier, 'seeded', 'Demonstration record loaded.');
        }

        $this->seedPendingWork($users, $staff);

        // Certificates already issued for completed records
        $issuedFor = [
            ['Smart Water Quality Monitoring System for Aquaculture Ponds in Dulag, Leyte', 'h.ortega@evsu.edu.ph', 'completion', 'promotion'],
            ['Development of a Low-Cost Seismic Retrofitting Technique for Public School Buildings', 'faculty@evsu.edu.ph', 'participation', 'reclassification'],
            ['Calamansi-Based Functional Juice Beverage with Extended Shelf Life', 'g.abella@evsu.edu.ph', 'utilization', 'ipcr'],
        ];
        foreach ($issuedFor as $i => [$title, $email, $type, $purpose]) {
            $project = Project::where('title', $title)->first();
            Certificate::create([
                'project_id' => $project->id, 'user_id' => $users[$email]->id, 'type' => $type, 'purpose' => $purpose,
                'status' => 'issued', 'serial_no' => 'ORIES-'.strtoupper(substr($project->office, 0, 3)).'-'.$year.'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'verification_code' => strtoupper(substr(md5($title.$email), 0, 12)),
                'issued_by' => $director($project->office)->id, 'issued_at' => now()->subDays(20 - $i),
            ]);
        }
        Certificate::create([
            'project_id' => Project::where('title', 'Thermal Performance of Coconut Coir Composite Panels for Tropical Housing')->value('id'),
            'user_id' => $users['a.tan@evsu.edu.ph']->id, 'type' => 'publication', 'purpose' => 'promotion', 'status' => 'requested',
            'request_remarks' => 'For faculty promotion this semester.',
        ]);

        $targets = [
            'research' => ['proposals' => 8, 'ongoing' => 6, 'completed' => 4, 'publications' => 5, 'scopus' => 2, 'commercialization' => 0, 'policy' => 1, 'technology_transfer' => 1],
            'innovation' => ['proposals' => 4, 'ongoing' => 3, 'completed' => 2, 'publications' => 1, 'scopus' => 0, 'commercialization' => 2, 'policy' => 0, 'technology_transfer' => 1],
            'extension' => ['proposals' => 6, 'ongoing' => 4, 'completed' => 4, 'publications' => 0, 'scopus' => 0, 'commercialization' => 0, 'policy' => 0, 'technology_transfer' => 0],
        ];
        foreach ($targets as $office => $metrics) {
            foreach ($metrics as $metric => $value) {
                Target::updateOrCreate(['year' => $year, 'office' => $office, 'metric' => $metric], ['value' => $value]);
            }
        }
    }

    /**
     * A few submissions waiting in the verification queue, each with a small MOV file.
     */
    private function seedPendingWork(array $users, callable $staff): void
    {
        $storage = app(MovStorage::class);
        $pdf = $this->samplePdf();

        $lead = $users['faculty@evsu.edu.ph'];
        $proposal = Project::create([
            'title' => 'Predictive Model for Student Attrition in State Universities Using Machine Learning',
            'office' => 'research', 'abstract' => 'Fictional pending proposal used to demonstrate the Crosschecking Module.',
            'lead_id' => $lead->id, 'campus_id' => $lead->campus_id, 'unit_id' => $lead->unit_id,
            'stage' => 'proposal', 'verification_status' => 'pending', 'funding_source' => 'EVSU Internal Research Fund',
            'budget' => 120000, 'proposed_at' => now()->toDateString(),
        ]);
        $proposal->members()->create(['user_id' => $lead->id, 'name' => $lead->name, 'role' => 'lead']);
        $update = $proposal->updates()->create([
            'submitted_by' => $lead->id, 'type' => 'proposal', 'status' => 'pending',
            'duplicate_matches' => app(\App\Services\DuplicateTitleChecker::class)->findMatches($proposal->title, $proposal->id),
        ]);
        $storage->storeContents($pdf, 'Proposal - Student Attrition Model.pdf', 'application/pdf', $proposal, $update, $lead, 'upload');

        $ongoing = Project::where('title', 'Flood Early Warning Mobile Application for Barangays Along the Binahaan River')->first();
        $u = $ongoing->updates()->create([
            'submitted_by' => $ongoing->lead_id, 'type' => 'stage', 'from_stage' => 'ongoing', 'to_stage' => 'completed',
            'data' => ['date' => now()->subDays(5)->toDateString()], 'remarks' => 'Terminal report approved by the college research committee.', 'status' => 'pending',
        ]);
        $storage->storeContents($pdf, 'Terminal Report - Flood Early Warning App.pdf', 'application/pdf', $ongoing, $u, $ongoing->lead, 'upload');

        $extension = Project::where('title', 'Community-Based Mangrove Rehabilitation and Livelihood Program')->first();
        $u = $extension->updates()->create([
            'submitted_by' => $extension->lead_id, 'type' => 'outcome', 'status' => 'pending',
            'data' => ['type' => 'policy', 'title' => 'Barangay ordinance on mangrove protection zones', 'venue' => 'Barangay 88, San Jose, Tacloban City', 'date' => now()->subDays(12)->toDateString()],
        ]);
        $storage->storeContents($pdf, 'Barangay Ordinance No. 2026-04.pdf', 'application/pdf', $extension, $u, $extension->lead, 'upload');
    }

    private function samplePdf(): string
    {
        $text = 'Sample Means of Verification - ORIES-IS demonstration file';
        $stream = "BT /F1 14 Tf 60 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
