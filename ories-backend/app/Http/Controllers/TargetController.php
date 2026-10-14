<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) ($request->validate(['year' => 'nullable|integer|min:1990|max:2100'])['year'] ?? now()->year);

        return response()->json([
            'year' => $year,
            'targets' => Target::where('year', $year)->get(['office', 'metric', 'value']),
        ]);
    }

    /**
     * Saves the full set of targets for one office and year.
     */
    public function save(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('staff', 'director'), 403);

        $data = $request->validate([
            'year' => 'required|integer|min:1990|max:2100',
            'office' => ['required', Rule::in(User::OFFICES)],
            'targets' => 'required|array',
            'targets.*' => 'nullable|integer|min:0',
        ]);
        abort_unless($user->office === null || $user->office === $data['office'], 403);

        foreach (Target::METRICS as $metric) {
            $value = $data['targets'][$metric] ?? null;
            $where = ['year' => $data['year'], 'office' => $data['office'], 'metric' => $metric];

            $value === null
                ? Target::where($where)->delete()
                : Target::updateOrCreate($where, ['value' => $value]);
        }

        return response()->json(['message' => 'Targets saved.']);
    }
}
