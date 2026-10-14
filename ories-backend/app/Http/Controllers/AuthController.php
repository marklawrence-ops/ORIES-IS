<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', mb_strtolower(trim($credentials['email'])))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid login credentials.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => $user->status === 'pending'
                    ? 'Your account is awaiting verification by the ORIES office.'
                    : 'Your account has been deactivated. Contact the ORIES office.',
            ], 403);
        }

        $token = $user->createToken('ories_auth_token', ['*'], now()->addDay())->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->present($user),
        ]);
    }

    /**
     * Faculty and non-teaching personnel register themselves; ORIES staff verify the account
     * before it can sign in, until the system is connected to the university's main directory.
     */
    public function register(Request $request, Notifier $notifier): JsonResponse
    {
        // Lowercased before validation so the uniqueness check ignores letter case
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'employee_type' => 'required|in:teaching,non_teaching',
            'position' => 'nullable|string|max:255',
            'campus_id' => 'required|integer|exists:campuses,id',
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('campus_id', $request->input('campus_id'))],
        ]);

        $user = User::create([...$data, 'role' => 'faculty', 'status' => 'pending']);

        $notifier->send(
            User::administrators()->get(),
            'New account awaiting approval',
            $user->name.' ('.$user->email.')',
            '/admin/users?status=pending',
        );

        return response()->json(['message' => 'Registration received. You can sign in once the ORIES office verifies your account.'], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->present($request->user()));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    private function present(User $user): array
    {
        $user->loadMissing('campus:id,name', 'unit:id,name,code');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'office' => $user->office,
            'position' => $user->position,
            'employee_type' => $user->employee_type,
            'campus' => $user->campus,
            'unit' => $user->unit,
            'can_manage_users' => $user->canManageUsers(),
        ];
    }
}
