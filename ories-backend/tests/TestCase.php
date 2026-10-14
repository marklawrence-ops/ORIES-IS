<?php

namespace Tests;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function actingAsUser(string $email): User
    {
        $user = $this->user($email);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function mov(string $name = 'mov.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    /**
     * Submits a proposal as the given faculty member and returns the created project.
     */
    protected function submitProposal(string $email = 'faculty@evsu.edu.ph', array $overrides = []): Project
    {
        $this->actingAsUser($email);

        $this->post('/api/projects', [
            'title' => 'Rainwater Harvesting Design for Rural Schools in Leyte',
            'office' => 'research',
            'abstract' => 'Test proposal.',
            'files' => [$this->mov()],
            ...$overrides,
        ], ['Accept' => 'application/json'])->assertCreated();

        return Project::latest('id')->firstOrFail();
    }

    protected function verifyPending(Project $project, string $verifier = 'staff.research@evsu.edu.ph'): void
    {
        $this->actingAsUser($verifier);
        $update = $project->updates()->where('status', 'pending')->firstOrFail();
        $this->postJson("/api/verification/{$update->id}/verify")->assertOk();
    }
}
