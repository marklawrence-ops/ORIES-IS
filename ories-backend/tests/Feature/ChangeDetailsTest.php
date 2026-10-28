<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Unit;
use Tests\TestCase;

/**
 * Improvement I2: a verified record's details, members, and tags change only through a verified update.
 */
class ChangeDetailsTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->submitProposal(overrides: [
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-15',
            'budget' => '150000',
            'members' => [['user_id' => $this->user('head@evsu.edu.ph')->id], ['name' => 'Dr. External Partner']],
            'tags' => [['type' => 'partner', 'label' => 'DOST Region VIII']],
        ]);
        $this->verifyPending($this->project);
        $this->project->refresh();
    }

    /**
     * The form always sends every field; this is the record as it stands, with the given changes.
     */
    private function form(array $changes = []): array
    {
        $p = $this->project->fresh(['members', 'tags']);

        return [
            'title' => $p->title,
            'abstract' => $p->abstract,
            'keywords' => $p->keywords,
            'funding_source' => $p->funding_source,
            'budget' => $p->budget,
            'start_date' => $p->start_date?->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
            'members' => $p->members->where('role', 'member')->map(fn ($m) => ['user_id' => $m->user_id, 'name' => $m->name])->values()->all(),
            'tags' => $p->tags->map(fn ($t) => $t->only(['type', 'unit_id', 'campus_id', 'label']))->all(),
            'remarks' => 'Extension approved by the University Research Council.',
            'files' => [$this->mov('extension-approval.pdf')],
            ...$changes,
        ];
    }

    private function submit(array $changes, string $email = 'faculty@evsu.edu.ph')
    {
        $this->actingAsUser($email);

        return $this->post("/api/projects/{$this->project->id}/details", $this->form($changes), ['Accept' => 'application/json']);
    }

    public function test_extension_is_applied_only_after_verification(): void
    {
        $this->submit(['end_date' => '2027-06-30', 'budget' => '150000.00'])->assertCreated();

        $update = $this->project->updates()->latest('id')->first();
        $this->assertSame('details', $update->type);
        $this->assertSame(['end_date' => ['from' => '2026-12-15', 'to' => '2027-06-30']], $update->data['fields'], 'Only changed fields are kept; 150000 and 150000.00 are the same budget.');
        $this->assertArrayNotHasKey('members', $update->data);
        $this->assertSame('Change of details: end date', $update->summary());
        $this->assertSame('2026-12-15', $this->project->fresh()->end_date->toDateString(), 'Unchanged until verified.');

        $this->verifyPending($this->project);
        $this->assertSame('2027-06-30', $this->project->fresh()->end_date->toDateString());
    }

    public function test_members_and_tags_are_replaced_on_verification(): void
    {
        $unit = Unit::first();
        $this->submit([
            'members' => [['user_id' => $this->user('head@evsu.edu.ph')->id], ['name' => 'Engr. New Collaborator']],
            'tags' => [['type' => 'partner', 'label' => 'DOST Region VIII'], ['type' => 'unit', 'unit_id' => $unit->id]],
        ])->assertCreated();

        $update = $this->project->updates()->latest('id')->first();
        $this->assertSame('Change of details: members, contributor tags', $update->summary());
        $this->assertContains('Dr. External Partner', array_column($update->data['members']['from'], 'name'));
        $this->assertSame(2, $this->project->members()->where('role', 'member')->count(), 'Unchanged until verified.');

        $this->verifyPending($this->project);

        $names = $this->project->members()->orderBy('id')->pluck('name')->all();
        $this->assertContains('Engr. New Collaborator', $names);
        $this->assertNotContains('Dr. External Partner', $names);
        $this->assertSame(1, $this->project->members()->where('role', 'lead')->count(), 'The lead is kept.');
        $this->assertContains("{$unit->name} ({$unit->campus->name})", $this->project->tags()->pluck('label')->all());
    }

    public function test_returned_change_leaves_the_record_untouched(): void
    {
        $this->submit(['title' => 'A Completely Different Title for the Study'])->assertCreated();
        $update = $this->project->updates()->latest('id')->first();

        $this->actingAsUser('staff.research@evsu.edu.ph');
        $this->postJson("/api/verification/{$update->id}/return", ['remarks' => 'The council approval does not mention a new title.'])->assertOk();

        $this->assertSame($this->project->title, $this->project->fresh()->title);
        $this->assertSame('verified', $this->project->fresh()->verification_status, 'A returned change does not unverify the record.');

        // The lead may try again
        $this->actingAsUser('faculty@evsu.edu.ph');
        $this->getJson("/api/projects/{$this->project->id}")->assertJsonPath('permissions.can_change_details', true);
    }

    public function test_change_needs_a_reason_a_file_and_an_actual_change(): void
    {
        $this->submit(['remarks' => '', 'files' => []])->assertUnprocessable()->assertJsonValidationErrors(['remarks', 'files']);
        $this->submit([])->assertUnprocessable()->assertJsonPath('message', 'Nothing was changed.');
        $this->submit(['start_date' => '2027-01-01', 'end_date' => '2026-12-31'])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_only_the_lead_can_change_details_and_only_one_update_at_a_time(): void
    {
        $this->submit(['end_date' => '2027-06-30'], 'head@evsu.edu.ph')->assertForbidden();

        $this->submit(['end_date' => '2027-06-30'])->assertCreated();
        $this->submit(['end_date' => '2027-07-30'])->assertUnprocessable();
        $this->getJson("/api/projects/{$this->project->id}")->assertJsonPath('permissions.can_change_details', false);
    }

    public function test_new_title_is_crosschecked(): void
    {
        $other = $this->submitProposal('head@evsu.edu.ph', ['title' => 'Solar Dryer for Copra Processing in Leyte Villages']);
        $this->verifyPending($other);

        $this->submit(['title' => 'Solar Dryer for Copra Processing in Leyte Village'])->assertCreated();

        $matches = $this->project->updates()->latest('id')->first()->duplicate_matches;
        $this->assertSame($other->id, $matches[0]['project_id']);
    }

    public function test_extension_clears_the_overdue_flag(): void
    {
        $this->project->update(['stage' => 'ongoing']);
        $this->travelTo('2027-01-10');
        $this->assertSame('overdue', $this->project->fresh()->deadline());

        $this->submit(['end_date' => '2027-06-30'])->assertCreated();
        $this->verifyPending($this->project);

        $this->assertNull($this->project->fresh()->deadline());
    }
}
