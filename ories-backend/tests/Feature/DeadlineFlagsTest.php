<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Project;
use Tests\TestCase;

/**
 * Improvement I1: ongoing records past or near their end date are flagged.
 */
class DeadlineFlagsTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-28 09:00');

        $records = [
            'overdue' => ['ongoing', 'verified', '2026-10-01'],
            'ending_soon' => ['ongoing', 'verified', '2026-11-15'],
            'ends_today' => ['ongoing', 'verified', '2026-10-28'],
            'far_off' => ['ongoing', 'verified', '2027-03-01'],
            'completed_late' => ['completed', 'verified', '2026-09-01'],
            'unverified_late' => ['ongoing', 'pending', '2026-09-01'],
        ];

        foreach ($records as $key => [$stage, $status, $end]) {
            $project = $this->submitProposal(overrides: ['title' => "Deadline Test Record {$key}"]);
            $project->update(['stage' => $stage, 'verification_status' => $status, 'start_date' => '2025-01-01', 'end_date' => $end]);
            if ($status === 'verified') {
                $project->updates()->update(['status' => 'verified']);
            }
            $this->ids[$key] = $project->id;
        }
    }

    private function idsFor(string ...$keys): array
    {
        return collect($keys)->map(fn ($k) => $this->ids[$k])->sort()->values()->all();
    }

    public function test_scopes_flag_only_verified_ongoing_records(): void
    {
        $this->assertSame($this->idsFor('overdue'), Project::pastEndDate()->orderBy('id')->pluck('id')->all());
        $this->assertSame($this->idsFor('ending_soon', 'ends_today'), Project::endingSoon()->orderBy('id')->pluck('id')->all());

        $this->assertSame('overdue', Project::find($this->ids['overdue'])->deadline());
        $this->assertSame('ending_soon', Project::find($this->ids['ends_today'])->deadline());
        $this->assertNull(Project::find($this->ids['far_off'])->deadline());
        $this->assertNull(Project::find($this->ids['completed_late'])->deadline());
        $this->assertNull(Project::find($this->ids['unverified_late'])->deadline());
    }

    public function test_dashboard_tiles_match_the_lists_they_open(): void
    {
        foreach (['director.research@evsu.edu.ph', 'vp@evsu.edu.ph', 'head@evsu.edu.ph', 'faculty@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $tasks = collect($this->getJson('/api/dashboard')->assertOk()->json('tasks'));

            $overdue = $tasks->first(fn ($t) => str_contains($t['link'], 'deadline=overdue'));
            $this->assertSame(1, $overdue['value'], $email);
            $this->assertSame('red', $overdue['tone']);
            $this->getJson('/api'.$overdue['link'])->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $this->ids['overdue'])
                ->assertJsonPath('data.0.deadline', 'overdue');

            $soon = $tasks->first(fn ($t) => str_contains($t['link'], 'deadline=ending_soon'));
            $this->assertSame(2, $soon['value'], $email);
            $this->getJson('/api'.$soon['link'])->assertJsonPath('total', 2);
        }
    }

    public function test_tiles_stay_within_the_users_area(): void
    {
        $this->actingAsUser('director.extension@evsu.edu.ph');
        $tasks = collect($this->getJson('/api/dashboard')->json('tasks'));

        $this->assertSame(0, $tasks->first(fn ($t) => str_contains($t['link'], 'deadline=overdue'))['value']);
    }

    public function test_record_page_reports_its_deadline(): void
    {
        $this->actingAsUser('faculty@evsu.edu.ph');

        $this->getJson("/api/projects/{$this->ids['overdue']}")->assertJsonPath('project.deadline', 'overdue');
        $this->getJson("/api/projects/{$this->ids['far_off']}")->assertJsonPath('project.deadline', null);
    }

    public function test_weekly_reminder_skips_records_with_an_update_waiting(): void
    {
        // A completion report already submitted for the overdue record
        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$this->ids['overdue']}/updates", ['type' => 'stage', 'to_stage' => 'completed', 'files' => [$this->mov()]], ['Accept' => 'application/json'])->assertCreated();

        $this->artisan('ories:remind-deadlines')->assertSuccessful();

        $this->assertSame(0, Notification::where('title', 'Record past its end date')->count());
        $this->assertSame(2, Notification::where('title', 'Record ending soon')->count());

        $this->artisan('ories:remind-stale')->assertSuccessful();
        $this->assertFalse(
            Notification::where('title', 'Status update reminder')->where('link', "/projects/{$this->ids['overdue']}")->exists(),
            'Overdue records are covered by the deadline reminder, not the stale one.'
        );
    }

    public function test_weekly_reminder_names_the_end_date(): void
    {
        $this->artisan('ories:remind-deadlines')->assertSuccessful();

        $notice = Notification::where('title', 'Record past its end date')->sole();
        $this->assertSame("/projects/{$this->ids['overdue']}", $notice->link);
        $this->assertStringContainsString('October 1, 2026', $notice->body);
    }
}
