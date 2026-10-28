<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Project;
use Tests\TestCase;

/**
 * Fixes from the October 16 test run (bugs B1, B2, B3, B6, B7, B8).
 */
class DashboardAndListFixesTest extends TestCase
{
    private function stat(array $dashboard, string $key): array
    {
        return collect($dashboard['stats'])->firstWhere('key', $key);
    }

    public function test_stage_figures_open_lists_with_the_same_count(): void
    {
        $verified = $this->submitProposal();
        $this->verifyPending($verified);
        $this->submitProposal(overrides: ['title' => 'Pending Proposal on Abaca Fiber Grading']);

        $this->actingAsUser('vp@evsu.edu.ph');
        $proposals = $this->stat($this->getJson('/api/dashboard')->assertOk()->json(), 'proposals');
        $this->assertSame('verified', $proposals['filters']['verification_status']);

        $list = $this->getJson('/api/projects?'.http_build_query(['scope' => 'responsibility', ...$proposals['filters']]))->assertOk();
        $this->assertSame($proposals['value'], $list->json('total'));
    }

    public function test_task_tiles_open_matching_lists_and_count_only_uncorrected_returns(): void
    {
        $project = $this->submitProposal();
        $this->actingAsUser('staff.research@evsu.edu.ph');
        $update = $project->updates()->firstOrFail();
        $this->postJson("/api/verification/{$update->id}/return", ['remarks' => 'Attach the endorsement.'])->assertOk();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $tasks = collect($this->getJson('/api/dashboard')->json('tasks'))->keyBy('label');
        $returned = $tasks['My records returned for correction'];
        $this->assertSame(1, $returned['value']);
        $this->assertSame('/projects?scope=mine&activity=returned', $returned['link']);
        $this->getJson('/api/projects?scope=mine&activity=returned')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $project->id);

        // Correcting the proposal clears it from "returned" and moves it to "awaiting"
        $this->post("/api/projects/{$project->id}", ['title' => $project->title, 'office' => 'research', 'files' => [$this->mov()]], ['Accept' => 'application/json'])->assertOk();
        $tasks = collect($this->getJson('/api/dashboard')->json('tasks'))->keyBy('label');
        $this->assertSame(0, $tasks['My records returned for correction']['value']);
        $this->assertSame(1, $tasks['My records awaiting verification']['value']);
        $this->getJson('/api/projects?scope=mine&activity=returned')->assertJsonPath('total', 0);
        $this->getJson('/api/projects?scope=mine&activity=awaiting')->assertJsonPath('total', 1);
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $project = $this->submitProposal(overrides: ['title' => 'Yield Gains of 50% in Upland Rice']);
        $this->verifyPending($project);
        $this->submitProposal(overrides: ['title' => 'Unrelated Study on Coastal Erosion']);
        Project::query()->update(['verification_status' => 'verified']);

        $this->actingAsUser('vp@evsu.edu.ph');
        $this->getJson('/api/projects?q=%25')->assertJsonPath('total', 1);
        $this->getJson('/api/projects?q=50%25')->assertJsonPath('total', 1);
        $this->getJson('/api/projects?q=_')->assertJsonPath('total', 0);
    }

    public function test_leadership_recent_list_shows_only_verified_records(): void
    {
        $this->submitProposal(overrides: ['title' => 'Unverified Proposal Still Waiting']);

        $this->actingAsUser('vp@evsu.edu.ph');
        $titles = collect($this->getJson('/api/dashboard')->json('recent'))->pluck('title');
        $this->assertNotContains('Unverified Proposal Still Waiting', $titles);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $titles = collect($this->getJson('/api/dashboard')->json('recent'))->pluck('title');
        $this->assertContains('Unverified Proposal Still Waiting', $titles, 'Submitters still see their own pending records.');
    }

    public function test_record_lists_which_certificates_can_be_requested_and_why_not(): void
    {
        $project = $this->submitProposal();
        $this->verifyPending($project);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $types = $this->getJson("/api/projects/{$project->id}")->json('permissions.certificate_types');
        $this->assertNull($types['participation']);
        $this->assertStringContainsString('completed record', $types['completion']);
        $this->assertStringContainsString('publication', $types['publication']);
        $this->assertStringContainsString('utilization', $types['utilization']);

        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'participation', 'purpose' => 'promotion'])->assertCreated();
        $types = $this->getJson("/api/projects/{$project->id}")->json('permissions.certificate_types');
        $this->assertSame('You already requested this certificate.', $types['participation']);
    }

    public function test_unit_tags_name_their_campus(): void
    {
        $campus = Campus::with('units')->where('name', '!=', '')->get()->first(fn ($c) => $c->units->isNotEmpty());
        $unit = $campus->units->first();

        $project = $this->submitProposal(overrides: ['tags' => [['type' => 'unit', 'unit_id' => $unit->id]]]);

        $this->assertSame("{$unit->name} ({$campus->name})", $project->tags()->value('label'));
    }
}
