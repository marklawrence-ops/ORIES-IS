<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin Module: ORIES technical/office staff manage accounts and approve self-registrations.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $filters = $request->validate([
            'q' => 'nullable|string|max:255',
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => 'nullable|in:pending,active,inactive',
        ]);

        $users = User::query()
            ->with(['campus:id,name', 'unit:id,name,code'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($term).'%'])))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(25);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $data = $this->validated($request);
        $user = User::create([...$data, 'status' => 'active']);

        return response()->json(['message' => 'Account created.', 'user' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        if ($user->status !== 'active') {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Account updated.', 'user' => $user]);
    }

    public function approve(Request $request, User $user, Notifier $notifier): JsonResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        abort_unless($user->status === 'pending', 422, 'This account is not awaiting approval.');

        $user->update(['status' => 'active', 'email_verified_at' => now()]);
        $notifier->send($user, 'Account approved', 'Your ORIES-IS account is now active.', '/dashboard');

        return response()->json(['message' => 'Account approved.']);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        if ($request->has('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', Rule::in(User::ROLES)],
            'employee_type' => 'nullable|in:teaching,non_teaching',
            'office' => ['nullable', Rule::in(User::OFFICES)],
            'position' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'status' => [$user ? 'required' : 'nullable', 'in:pending,active,inactive'],
        ]);
    }
}
