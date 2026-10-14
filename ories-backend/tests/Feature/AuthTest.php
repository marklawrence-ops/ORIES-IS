<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_active_user_can_log_in_and_receives_role(): void
    {
        $this->postJson('/api/login', ['email' => 'faculty@evsu.edu.ph', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'faculty')
            ->assertJsonStructure(['access_token']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/login', ['email' => 'faculty@evsu.edu.ph', 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_self_registration_waits_for_staff_approval(): void
    {
        $campus = Campus::where('code', 'TAC')->first();

        $this->postJson('/api/register', [
            'name' => 'New Faculty',
            'email' => 'new.faculty@evsu.edu.ph',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'employee_type' => 'non_teaching',
            'campus_id' => $campus->id,
            'unit_id' => $campus->units()->first()->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'new.faculty@evsu.edu.ph', 'status' => 'pending', 'role' => 'faculty']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->user('admin@evsu.edu.ph')->id, 'title' => 'New account awaiting approval']);

        $this->postJson('/api/login', ['email' => 'new.faculty@evsu.edu.ph', 'password' => 'secret-pass-1'])->assertForbidden();

        $this->actingAsUser('admin@evsu.edu.ph');
        $this->postJson('/api/users/'.User::where('email', 'new.faculty@evsu.edu.ph')->value('id').'/approve')->assertOk();

        $this->postJson('/api/login', ['email' => 'new.faculty@evsu.edu.ph', 'password' => 'secret-pass-1'])->assertOk();
    }

    public function test_only_technical_staff_can_manage_accounts(): void
    {
        foreach (['faculty@evsu.edu.ph', 'head@evsu.edu.ph', 'director.research@evsu.edu.ph', 'vp@evsu.edu.ph', 'oup@evsu.edu.ph'] as $email) {
            $this->actingAsUser($email);
            $this->getJson('/api/users')->assertForbidden();
        }

        $this->actingAsUser('admin@evsu.edu.ph');
        $this->getJson('/api/users')->assertOk();
        $this->postJson('/api/users', [
            'name' => 'Created Director', 'email' => 'created@evsu.edu.ph', 'password' => 'password123',
            'role' => 'director', 'office' => 'extension',
        ])->assertCreated();
    }

    public function test_office_staff_cannot_administer_accounts(): void
    {
        $staff = $this->actingAsUser('staff.research@evsu.edu.ph');

        $this->getJson('/api/users')->assertForbidden();
        $this->putJson("/api/users/{$staff->id}", [
            'name' => $staff->name, 'email' => $staff->email, 'role' => 'staff', 'office' => null, 'status' => 'active',
        ])->assertForbidden();
        $this->assertSame('research', $staff->fresh()->office);
        $this->getJson('/api/me')->assertJsonPath('can_manage_users', false);

        $this->actingAsUser('admin@evsu.edu.ph');
        $this->getJson('/api/me')->assertJsonPath('can_manage_users', true);
    }

    public function test_email_letter_case_is_ignored(): void
    {
        $this->postJson('/api/login', ['email' => 'Faculty@EVSU.edu.ph', 'password' => 'password123'])->assertOk();

        $campus = Campus::with('units')->firstOrFail();
        $this->postJson('/api/register', [
            'name' => 'Duplicate Faculty',
            'email' => 'FACULTY@evsu.edu.ph',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'employee_type' => 'teaching',
            'campus_id' => $campus->id,
            'unit_id' => $campus->units->first()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $this->user('faculty@evsu.edu.ph')->update(['status' => 'inactive']);

        $this->postJson('/api/login', ['email' => 'faculty@evsu.edu.ph', 'password' => 'password123'])->assertForbidden();
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
    }
}
