<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the interactive ORIES organizational chart from live account data.
 */
class OrgChartController extends Controller
{
    public function index(): JsonResponse
    {
        $active = User::where('status', 'active')->with(['unit:id,name,code', 'campus:id,name'])->get();
        $person = fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'position' => $u->position, 'role' => $u->role];

        $offices = collect(User::OFFICES)->map(fn (string $office) => [
            'id' => 'office-'.$office,
            'type' => 'office',
            'label' => 'Office of '.ucfirst($office).($office === 'extension' ? ' Services' : ''),
            'office' => $office,
            'people' => $active->where('role', 'director')->where('office', $office)->map($person)->values(),
            'children' => [[
                'id' => 'staff-'.$office,
                'type' => 'group',
                'label' => 'Office and Technical Staff',
                'people' => $active->where('role', 'staff')->where('office', $office)->map($person)->values(),
                'children' => [],
            ]],
            'records' => Project::verified()->where('office', $office)->count(),
        ])->values();

        $campuses = Campus::with('units')->orderBy('id')->get()->map(fn (Campus $campus) => [
            'id' => 'campus-'.$campus->id,
            'type' => 'campus',
            'label' => $campus->name.' Campus',
            'campus_id' => $campus->id,
            'people' => [],
            'records' => Project::verified()->where('campus_id', $campus->id)->count(),
            'children' => $campus->units->map(fn ($unit) => [
                'id' => 'unit-'.$unit->id,
                'type' => 'unit',
                'label' => $unit->name,
                'unit_id' => $unit->id,
                'people' => $active->where('unit_id', $unit->id)->where('role', 'head')->map($person)->values(),
                'faculty_count' => $active->where('unit_id', $unit->id)->where('role', 'faculty')->count(),
                'records' => Project::verified()->where('unit_id', $unit->id)->count(),
                'children' => [],
            ])->values(),
        ]);

        $sharedStaff = $active->where('role', 'staff')->whereNull('office')->map($person)->values();

        return response()->json([
            'id' => 'oup',
            'type' => 'executive',
            'label' => 'Office of the University President',
            'people' => $active->where('role', 'oup')->map($person)->values(),
            'children' => [[
                'id' => 'vp',
                'type' => 'executive',
                'label' => 'Office of the Vice President for Research, Innovation, and Extension Services',
                'people' => $active->where('role', 'vp')->map($person)->values(),
                'children' => [
                    ...$offices,
                    ...($sharedStaff->isNotEmpty() ? [[
                        'id' => 'staff-shared',
                        'type' => 'group',
                        'label' => 'ORIES Technical Staff (System Administration)',
                        'people' => $sharedStaff,
                        'children' => [],
                    ]] : []),
                    [
                        'id' => 'campuses',
                        'type' => 'group',
                        'label' => 'Campuses, Colleges, and Units',
                        'people' => [],
                        'children' => $campuses,
                    ],
                ],
            ]],
        ]);
    }

    /**
     * Profile shown when a person is clicked on the chart.
     */
    public function person(Request $request, User $user): JsonResponse
    {
        $user->load(['unit:id,name,code', 'campus:id,name']);
        $projects = Project::query()->visibleTo($request->user())->involving($user)
            ->latest('updated_at')->limit(10)->get(['id', 'title', 'office', 'stage', 'verification_status']);

        return response()->json([
            'person' => $user->only(['id', 'name', 'position', 'role', 'office', 'employee_type']) + [
                'unit' => $user->unit,
                'campus' => $user->campus,
            ],
            'projects' => $projects,
        ]);
    }
}
