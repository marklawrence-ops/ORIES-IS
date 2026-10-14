<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationWorkflowTest extends TestCase
{
    public function test_faculty_submits_proposal_that_stays_pending_until_verified(): void
    {
        $project = $this->submitProposal();

        $this->assertSame('pending', $project->verification_status);
        $this->assertSame('proposal', $project->stage);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->user('staff.research@evsu.edu.ph')->id]);

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('disabled', $attachment->drive_status, 'Drive backup is off in tests, so the local copy stands alone.');

        $this->verifyPending($project);

        $this->assertSame('verified', $project->fresh()->verification_status);
        $this->assertDatabaseHas('notifications', ['user_id' => $project->lead_id, 'title' => 'Update verified']);
    }

    public function test_proposal_requires_a_means_of_verification(): void
    {
        $this->actingAsUser('faculty@evsu.edu.ph');

        $this->postJson('/api/projects', ['title' => 'No file attached', 'office' => 'research'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files');
    }

    public function test_only_verifiers_of_the_matching_office_can_verify(): void
    {
        $project = $this->submitProposal();
        $update = $project->updates()->first();

        foreach (['faculty@evsu.edu.ph', 'head@evsu.edu.ph', 'vp@evsu.edu.ph', 'oup@evsu.edu.ph', 'staff.extension@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->postJson("/api/verification/{$update->id}/verify")->assertForbidden();
        }

        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/verify")->assertOk();
    }

    public function test_returned_proposal_can_be_corrected_and_resubmitted(): void
    {
        $project = $this->submitProposal();
        $update = $project->updates()->first();

        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/return")->assertJsonValidationErrors('remarks');
        $this->postJson("/api/verification/{$update->id}/return", ['remarks' => 'Attach the signed proposal.'])->assertOk();
        $this->assertSame('returned', $project->fresh()->verification_status);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$project->id}", [
            'title' => 'Rainwater Harvesting Design for Rural Schools in Leyte (Revised)',
            'office' => 'research',
            'files' => [$this->mov('signed.pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('pending', $project->fresh()->verification_status);
        $this->assertSame(2, $project->updates()->count());
    }

    public function test_stage_and_outcome_updates_apply_only_after_verification(): void
    {
        $project = $this->submitProposal();
        $this->verifyPending($project);

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->postJson("/api/projects/{$project->id}/updates", ['type' => 'stage', 'to_stage' => 'ongoing'])
            ->assertJsonValidationErrors('files');
        $this->post("/api/projects/{$project->id}/updates", ['type' => 'stage', 'to_stage' => 'completed', 'files' => [$this->mov()]], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('to_stage');

        $this->post("/api/projects/{$project->id}/updates", [
            'type' => 'stage', 'to_stage' => 'ongoing', 'date' => '2026-03-01', 'files' => [$this->mov()],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('proposal', $project->fresh()->stage, 'Pending changes must not alter the official record.');

        $this->post("/api/projects/{$project->id}/updates", ['type' => 'stage', 'to_stage' => 'ongoing', 'files' => [$this->mov()]], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->verifyPending($project);
        $this->assertSame('ongoing', $project->fresh()->stage);
        $this->assertSame('2026-03-01', $project->fresh()->start_date->toDateString());

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->post("/api/projects/{$project->id}/updates", [
            'type' => 'outcome',
            'outcome' => ['type' => 'publication', 'title' => 'Rainwater Harvesting in Leyte', 'venue' => 'Journal X', 'indexing' => 'scopus', 'date' => '2026-06-01'],
            'files' => [$this->mov('article.pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(0, $project->outcomes()->count());

        $update = $project->updates()->where('status', 'pending')->firstOrFail();
        $this->verifyPending($project);
        $this->assertDatabaseHas('project_outcomes', ['project_id' => $project->id, 'type' => 'publication', 'indexing' => 'scopus']);

        // A second reviewer acting on the same update cannot apply it again
        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/verify")->assertUnprocessable();
        $this->postJson("/api/verification/{$update->id}/return", ['remarks' => 'Late.'])->assertUnprocessable();
        $this->assertSame(1, $project->outcomes()->count());
    }

    public function test_pending_files_are_visible_only_to_uploader_team_and_verifiers(): void
    {
        $project = $this->submitProposal();
        $attachment = Attachment::firstOrFail();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->get("/api/attachments/{$attachment->id}")->assertOk();

        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->get("/api/attachments/{$attachment->id}")->assertOk();

        foreach (['vp@evsu.edu.ph', 'head@evsu.edu.ph', 'staff.extension@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->get("/api/attachments/{$attachment->id}")->assertForbidden();
        }

        $this->verifyPending($project);

        foreach (['vp@evsu.edu.ph', 'head@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->get("/api/attachments/{$attachment->id}")->assertOk();
        }
    }

    public function test_unverified_records_are_hidden_from_unrelated_users(): void
    {
        $project = $this->submitProposal();
        $other = User::create([
            'name' => 'Other Faculty', 'email' => 'other@evsu.edu.ph', 'password' => 'password123',
            'role' => 'faculty', 'status' => 'active',
        ]);

        Sanctum::actingAs($other);
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(0, 'data');

        $this->verifyPending($project);

        Sanctum::actingAs($other);
        $this->getJson("/api/projects/{$project->id}")->assertOk()
            ->assertJsonPath('permissions.can_inspect', false);
    }

    public function test_viewers_and_staff_cannot_submit_records(): void
    {
        foreach (['vp@evsu.edu.ph', 'oup@evsu.edu.ph', 'staff.research@evsu.edu.ph', 'director.research@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->post('/api/projects', ['title' => 'X', 'office' => 'research', 'files' => [$this->mov()]], ['Accept' => 'application/json'])
                ->assertForbidden();
        }
    }

    public function test_activity_history_records_each_step(): void
    {
        $project = $this->submitProposal();
        $this->verifyPending($project);

        $this->assertSame(['submitted', 'verified'], $project->activity()->orderBy('id')->pluck('action')->all());
    }

    public function test_verification_queue_is_scoped_to_office(): void
    {
        $this->submitProposal();
        $this->submitProposal('faculty@evsu.edu.ph', ['title' => 'Coastal Clean-Up Drive', 'office' => 'extension']);

        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->getJson('/api/verification')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsUser('admin@evsu.edu.ph');
        $this->getJson('/api/verification')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(2, Project::count());
    }
}
