<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Certificate;
use App\Models\Project;
use App\Models\ProjectOutcome;
use App\Models\Target;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Reference data for forms and filters. Public so the registration page can use it.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'campuses' => Campus::with('units:id,campus_id,name,code,type')->orderBy('id')->get(['id', 'name', 'code']),
            'offices' => User::OFFICES,
            'roles' => User::ROLES,
            'stages' => Project::STAGES,
            'outcome_types' => ProjectOutcome::TYPES,
            'indexing' => ProjectOutcome::INDEXING,
            'certificate_types' => Certificate::TYPES,
            'certificate_purposes' => Certificate::PURPOSES,
            'target_metrics' => Target::METRICS,
            'duplicate_threshold' => (float) config('ories.duplicate_threshold'),
        ]);
    }

    /**
     * Account search used when adding project members.
     */
    public function people(Request $request): JsonResponse
    {
        $term = $request->validate(['q' => 'required|string|min:2|max:100'])['q'];

        return response()->json(
            User::query()
                ->where('status', 'active')
                ->whereIn('role', User::SUBMITTERS)
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->with('unit:id,code,name')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'position', 'unit_id'])
        );
    }
}
