<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Project;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    private function completedProject(): Project
    {
        $project = $this->submitProposal();
        $this->verifyPending($project);

        foreach (['ongoing', 'completed'] as $stage) {
            $this->actingAsUser('faculty@evsu.edu.ph');
            $this->post("/api/projects/{$project->id}/updates", ['type' => 'stage', 'to_stage' => $stage, 'files' => [$this->mov()]], ['Accept' => 'application/json'])
                ->assertCreated();
            $this->verifyPending($project);
        }

        return $project->fresh();
    }

    public function test_certificate_requires_an_eligible_verified_record(): void
    {
        $project = $this->submitProposal();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'participation', 'purpose' => 'promotion'])
            ->assertUnprocessable();

        $this->verifyPending($project);
        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'completion', 'purpose' => 'promotion'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A completion certificate requires a completed record.');
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'participation', 'purpose' => 'promotion'])
            ->assertCreated();
    }

    public function test_director_of_the_office_issues_a_verifiable_certificate(): void
    {
        $project = $this->completedProject();

        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'completion', 'purpose' => 'reclassification'])->assertCreated();
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'completion', 'purpose' => 'reclassification'])->assertUnprocessable();
        $certificate = Certificate::firstOrFail();

        foreach (['faculty@evsu.edu.ph', 'staff.research@evsu.edu.ph', 'director.extension@evsu.edu.ph', 'vp@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->postJson("/api/certificates/{$certificate->id}/issue")->assertForbidden();
        }

        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->postJson("/api/certificates/{$certificate->id}/issue")->assertOk();

        $certificate->refresh();
        $this->assertSame('issued', $certificate->status);
        $this->assertMatchesRegularExpression('/^ORIES-RES-\d{4}-0001$/', $certificate->serial_no);

        $this->getJson('/api/certificates/verify/'.$certificate->verification_code)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('certificate.recipient.name', $project->lead->name);

        $this->getJson('/api/certificates/verify/NOTAREALCODE')->assertNotFound()->assertJsonPath('valid', false);
    }

    public function test_serials_continue_from_the_last_issued_number_and_cannot_be_reissued(): void
    {
        $project = $this->completedProject();
        $this->actingAsUser('faculty@evsu.edu.ph');
        foreach (['completion', 'participation'] as $type) {
            $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => $type, 'purpose' => 'promotion'])->assertCreated();
        }
        [$first, $second] = Certificate::orderBy('id')->get()->all();

        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->postJson("/api/certificates/{$first->id}/issue")->assertOk();
        $this->postJson("/api/certificates/{$first->id}/issue")->assertUnprocessable();
        $this->postJson("/api/certificates/{$first->id}/decline", ['remarks' => 'Too late.'])->assertUnprocessable();
        $this->postJson("/api/certificates/{$second->id}/issue")->assertOk();

        $this->assertStringEndsWith('-0001', $first->fresh()->serial_no);
        $this->assertStringEndsWith('-0002', $second->fresh()->serial_no);
        $this->assertSame('issued', $first->fresh()->status);
    }

    public function test_declining_requires_remarks_and_notifies_the_requester(): void
    {
        $project = $this->completedProject();
        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->postJson('/api/certificates', ['project_id' => $project->id, 'type' => 'participation', 'purpose' => 'other'])->assertCreated();
        $certificate = Certificate::firstOrFail();

        $this->actingAsUser('director.research@evsu.edu.ph');
        $this->postJson("/api/certificates/{$certificate->id}/decline")->assertJsonValidationErrors('remarks');
        $this->postJson("/api/certificates/{$certificate->id}/decline", ['remarks' => 'Duplicate request.'])->assertOk();

        $this->assertDatabaseHas('notifications', ['user_id' => $project->lead_id, 'title' => 'Certificate request declined']);
    }
}
