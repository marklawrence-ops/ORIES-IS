<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Attempt authentication
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials.'
            ], 401);
        }

        // 3. Retrieve the user and generate a Sanctum token
        $user = User::where('email', $request->email)->firstOrFail();
        
        // Delete any existing tokens to enforce a single active session (optional but secure)
        $user->tokens()->delete(); 
        
        $token = $user->createToken('ories_auth_token')->plainTextToken;

        // 4. Return the token and essential user data for the Next.js frontend
        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, // Crucial for routing (vp_ories, director, head)
                'campus_id' => $user->campus_id,
                'department_name' => $user->department_name,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}