<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Target;
use Tests\TestCase;

class ReportsAndDashboardTest extends TestCase
{
    private function publishedProject(): Project
    {
        $project = $this->submitProposal();
        $this->verifyPending($project);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$project->id}/updates", [
            'type' => 'outcome',
            'outcome' => ['type' => 'publication', 'title' => 'Article', 'indexing' => 'scopus', 'date' => now()->toDateString()],
            'files' => [$this->mov()],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->verifyPending($project);

        return $project;
    }

    public function test_summary_report_counts_only_verified_records(): void
    {
        $this->publishedProject();
        $this->submitProposal('faculty@evsu.edu.ph', ['title' => 'Still pending proposal']);

        $this->actingAsUser('vp@evsu.edu.ph');
        $report = $this->getJson('/api/reports/summary?range=this_year&group_by=quarter')->assertOk()->json();

        $this->assertCount(4, $report['rows']);
        $this->assertSame(1, $report['totals']['proposals']);
        $this->assertSame(1, $report['totals']['publications']);
        $this->assertSame(1, $report['totals']['scopus']);
    }

    public function test_reports_support_monthly_grouping_and_long_ranges(): void
    {
        $this->publishedProject();
        $this->actingAsUser('vp@evsu.edu.ph');

        $this->getJson('/api/reports/summary?range=this_year&group_by=month')->assertOk()->assertJsonCount(12, 'rows');
        $this->getJson('/api/reports/summary?range=last_10_years&group_by=year')->assertOk()->assertJsonCount(10, 'rows');
        $this->getJson('/api/reports/summary?range=last_3_years')->assertOk()->assertJsonCount(3, 'rows');
        $this->getJson('/api/reports/summary?range=custom')->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_reports_export_to_csv(): void
    {
        $this->publishedProject();
        $this->actingAsUser('director.research@evsu.edu.ph');

        $response = $this->get('/api/reports/projects?range=this_year&format=csv')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Rainwater Harvesting Design for Rural Schools in Leyte', $response->streamedContent());
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $project = $this->submitProposal(overrides: ['title' => '=HYPERLINK("http://example.com","Click")']);
        $this->verifyPending($project);
        $this->actingAsUser('director.research@evsu.edu.ph');

        $csv = $this->get('/api/reports/projects?range=this_year&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_ipcr_report_counts_each_persons_records(): void
    {
        $this->publishedProject();
        $member = $this->user('head@evsu.edu.ph');
        $project = $this->submitProposal(overrides: [
            'title' => 'Second Record With a Co-Researcher',
            'members' => [['user_id' => $member->id]],
        ]);
        $this->verifyPending($project);

        $this->actingAsUser('vp@evsu.edu.ph');
        $rows = collect($this->getJson('/api/reports/ipcr?range=this_year')->assertOk()->json('rows'))->keyBy('name');

        $lead = $this->user('faculty@evsu.edu.ph');
        $this->assertSame(2, $rows[$lead->name]['proposals']);
        $this->assertSame(1, $rows[$lead->name]['publications']);
        $this->assertSame(1, $rows[$member->name]['proposals']);
        $this->assertSame(0, $rows[$member->name]['publications']);
    }

    public function test_reports_are_scoped_to_the_requesting_role(): void
    {
        $this->publishedProject();

        $this->actingAsUser('director.extension@evsu.edu.ph');
        $this->getJson('/api/reports/projects?range=this_year')->assertOk()->assertJsonCount(0, 'rows');

        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->getJson('/api/reports/projects?range=this_year')->assertOk()->assertJsonCount(1, 'rows');

        $this->actingAsUser('faculty@evsu.edu.ph');
        $rows = $this->getJson('/api/reports/ipcr?range=this_year')->assertOk()->json('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('Engr. Andrea C. Bautista', $rows[0]['name']);
    }

    public function test_opcr_report_compares_targets_with_accomplishments(): void
    {
        $this->publishedProject();
        Target::create(['year' => now()->year, 'office' => 'research', 'metric' => 'publications', 'value' => 4]);

        $this->actingAsUser('vp@evsu.edu.ph');
        $rows = collect($this->getJson('/api/reports/opcr?office=research')->assertOk()->json('rows'));
        $publications = $rows->firstWhere('metric', 'Publications');

        $this->assertSame(4, $publications['target']);
        $this->assertSame(1, $publications['accomplished']);
        $this->assertSame('25%', $publications['rate']);
    }

    public function test_dashboard_stats_drill_down_to_matching_people_and_records(): void
    {
        $project = $this->publishedProject();
        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$project->id}/updates", ['type' => 'stage', 'to_stage' => 'ongoing', 'files' => [$this->mov()]], ['Accept' => 'application/json']);
        $this->verifyPending($project);

        $this->actingAsUser('vp@evsu.edu.ph');
        $dashboard = $this->getJson('/api/dashboard')->assertOk()->json();
        $ongoing = collect($dashboard['stats'])->firstWhere('key', 'ongoing');
        $researchers = collect($dashboard['people'])->firstWhere('key', 'active_research');

        $this->assertSame(1, $ongoing['value']);
        $this->assertSame(1, $researchers['value']);

        $this->getJson('/api/projects?scope=responsibility&'.http_build_query($ongoing['filters']))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/dashboard/people?office=research')->assertOk()->assertJsonPath('people.0.name', 'Engr. Andrea C. Bautista');
    }

    public function test_search_finds_records_by_title_member_and_tag(): void
    {
        $this->submitProposal('faculty@evsu.edu.ph', [
            'tags' => [['type' => 'partner', 'label' => 'DOST Region VIII']],
            'members' => [['name' => 'External Collaborator Juan']],
        ]);
        $this->verifyPending(Project::firstOrFail());

        $this->actingAsUser('oup@evsu.edu.ph');
        foreach (['rainwater', 'DOST', 'collaborator juan'] as $term) {
            $this->getJson('/api/projects?q='.urlencode($term))->assertOk()->assertJsonCount(1, 'data');
        }
        $this->getJson('/api/projects?q=nonexistent')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_org_chart_lists_the_ories_hierarchy(): void
    {
        $this->actingAsUser('faculty@evsu.edu.ph');
        $chart = $this->getJson('/api/org-chart')->assertOk()->json();

        $this->assertSame('Office of the University President', $chart['label']);
        $vp = $chart['children'][0];
        $this->assertSame('Dr. Ramon S. Villanueva', $vp['people'][0]['name']);
        $this->assertSame(['office-research', 'office-innovation', 'office-extension'], array_slice(array_column($vp['children'], 'id'), 0, 3));
    }
}
