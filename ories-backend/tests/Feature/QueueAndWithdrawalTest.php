<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Project;
use Tests\TestCase;

/**
 * Improvements I3–I6: withdrawing a submission, notifying the whole team, reminding verifiers,
 * and moving through the verification queue.
 */
class QueueAndWithdrawalTest extends TestCase
{
    private function verifiedProject(array $overrides = []): Project
    {
        $project = $this->submitProposal(overrides: $overrides);
        $this->verifyPending($project);

        return $project->fresh();
    }

    private function submitStage(Project $project, string $email = 'faculty@evsu.edu.ph')
    {
        $this->actingAsUser($email);

        return $this->post("/api/projects/{$project->id}/updates", [
            'type' => 'stage',
            'to_stage' => 'ongoing',
            'files' => [$this->mov('wrong-file.pdf')],
        ], ['Accept' => 'application/json']);
    }

    public function test_submitter_can_withdraw_a_pending_update_and_submit_again(): void
    {
        $project = $this->verifiedProject(['members' => [['user_id' => $this->user('head@evsu.edu.ph')->id]]]);
        $this->submitStage($project)->assertCreated();
        $update = $project->updates()->where('status', 'pending')->firstOrFail();

        $this->actingAsUser('head@evsu.edu.ph');
        $this->getJson("/api/projects/{$project->id}")->assertJsonPath('permissions.can_withdraw', false);
        $this->postJson("/api/updates/{$update->id}/withdraw")->assertForbidden();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->getJson("/api/projects/{$project->id}")->assertJsonPath('permissions.can_withdraw', true);
        $this->postJson("/api/updates/{$update->id}/withdraw")->assertOk();

        $this->assertSame('withdrawn', $update->fresh()->status);
        $this->assertSame('proposal', $project->fresh()->stage, 'A withdrawn update changes nothing.');
        $this->assertSame('verified', $project->fresh()->verification_status);
        $this->getJson("/api/projects/{$project->id}")
            ->assertJsonPath('permissions.can_submit_update', true)
            ->assertJsonPath('permissions.can_withdraw', false);
        $this->postJson("/api/updates/{$update->id}/withdraw")->assertStatus(422);

        // The verifier can no longer act on it, and it leaves the queue
        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/verify")->assertStatus(422);
        $this->getJson('/api/verification')->assertJsonCount(0, 'data');

        $this->submitStage($project)->assertCreated();
    }

    public function test_withdrawn_proposal_goes_back_to_the_lead_for_correction(): void
    {
        $project = $this->submitProposal();
        $update = $project->updates()->firstOrFail();

        $this->postJson("/api/updates/{$update->id}/withdraw")->assertOk();

        $this->assertSame('returned', $project->fresh()->verification_status);
        $this->getJson("/api/projects/{$project->id}")->assertJsonPath('permissions.can_resubmit', true);
        $this->assertDatabaseHas('activity_logs', ['project_id' => $project->id, 'action' => 'withdrawn']);

        // Its files were never checked, so the resubmission must bring a new one
        $resubmit = fn (array $files) => $this->post("/api/projects/{$project->id}", [
            'title' => $project->title, 'office' => 'research', 'files' => $files,
        ], ['Accept' => 'application/json']);
        $resubmit([])->assertStatus(422)->assertJsonValidationErrors('files');
        $resubmit([$this->mov('right-file.pdf')])->assertOk();
        $this->assertSame(1, $project->updates()->where('status', 'pending')->firstOrFail()->attachments()->count());
    }

    public function test_a_returned_proposal_can_be_resubmitted_without_new_files(): void
    {
        $project = $this->submitProposal();
        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson('/api/verification/'.$project->updates()->value('id').'/return', ['remarks' => 'Correct the title.'])->assertOk();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$project->id}", ['title' => 'Rainwater Harvesting Systems for Rural Schools', 'office' => 'research'], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_lead_and_members_are_notified_when_an_update_is_decided(): void
    {
        $project = $this->verifiedProject(['members' => [['user_id' => $this->user('head@evsu.edu.ph')->id], ['name' => 'Dr. No Account']]]);
        $lead = $this->user('faculty@evsu.edu.ph');
        $member = $this->user('head@evsu.edu.ph');
        Notification::query()->delete();

        // A member submits; the lead hears about the decision too
        $this->submitStage($project, 'head@evsu.edu.ph')->assertCreated();
        $update = $project->updates()->where('status', 'pending')->firstOrFail();
        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/return", ['remarks' => 'Attach the signed notice to proceed.'])->assertOk();

        foreach ([$lead, $member] as $user) {
            $this->assertSame(1, Notification::where('user_id', $user->id)->where('title', 'Update returned for correction')->count());
        }
        $this->assertStringContainsString('Attach the signed notice to proceed.', Notification::where('user_id', $lead->id)->value('body'));

        $this->submitStage($project)->assertCreated();
        $this->verifyPending($project);
        foreach ([$lead, $member] as $user) {
            $this->assertSame(1, Notification::where('user_id', $user->id)->where('title', 'Update verified')->count());
        }
    }

    public function test_review_response_points_to_the_next_item_in_the_queue(): void
    {
        $first = $this->submitProposal();
        $second = $this->submitProposal(overrides: ['title' => 'Community Seaweed Farming Livelihood Program']);
        $this->submitProposal(overrides: ['title' => 'Bamboo Composite Panels for Classrooms', 'office' => 'innovation']);

        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson('/api/verification/'.$first->updates()->value('id').'/verify')
            ->assertOk()
            ->assertJsonPath('next.project_id', $second->id)
            ->assertJsonPath('next.remaining', 1);

        // The innovation proposal is not in the research verifier's queue
        $this->postJson('/api/verification/'.$second->updates()->value('id').'/return', ['remarks' => 'Attach the endorsement.'])
            ->assertOk()
            ->assertJsonPath('next', null);
    }

    public function test_old_submissions_are_highlighted_and_verifiers_are_reminded(): void
    {
        $this->travelTo('2026-10-19 09:00'); // Monday
        $old = $this->submitProposal();
        $this->travelTo('2026-10-26 10:00');
        $this->submitProposal(overrides: ['title' => 'Community Seaweed Farming Livelihood Program']);

        $this->travelTo('2026-10-27 09:30'); // six working days after the first submission
        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->getJson('/api/verification')
            ->assertJsonPath('verify_within_days', 5)
            ->assertJsonPath('waiting_too_long', 1)
            ->assertJsonPath('data.0.project_id', $old->id)
            ->assertJsonPath('data.0.waiting_days', 6)
            ->assertJsonPath('data.0.waiting_too_long', true)
            ->assertJsonPath('data.1.waiting_too_long', false);

        $tile = collect($this->getJson('/api/dashboard')->json('tasks'))->firstWhere('label', 'Waiting more than 5 working days');
        $this->assertSame(1, $tile['value']);

        Notification::query()->delete();
        $this->artisan('ories:remind-verifiers')->assertSuccessful();

        $reminded = Notification::where('title', 'Submissions waiting for verification')->pluck('user_id');
        foreach (['staff.research@evsu.edu.ph', 'director.research@evsu.edu.ph', 'admin@evsu.edu.ph'] as $email) {
            $this->assertContains($this->user($email)->id, $reminded, $email.' verifies research records.');
        }
        $this->assertNotContains($this->user('staff.innovation@evsu.edu.ph')->id, $reminded);
    }
}
