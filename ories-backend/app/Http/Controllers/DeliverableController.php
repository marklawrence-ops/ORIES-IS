<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliverableController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate the incoming Next.js payload
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'office_type' => 'required|in:extension,research,innovation',
            'due_date' => 'required|date',
            'mov_link' => 'nullable|string',
            'status' => 'required|string|in:draft,submitted',
            'tags' => 'nullable|array',
            'tags.*.type' => 'required_with:tags|in:department,campus,faculty',
            'tags.*.value' => 'required_with:tags|string|max:255',
        ]);

        $user = $request->user();

        // 2. Use a Database Transaction to ensure both Deliverable and Tags save safely
        DB::beginTransaction();

        try {
            // Create the main deliverable record (Automatically sets status to 'submitted')
            $deliverable = Deliverable::create([
                'title' => $validated['title'],
                'office_type' => $validated['office_type'],
                'campus_id' => $user->campus_id, // Inherited from the Head's profile
                'head_id' => $user->id,
                'status' => 'submitted', // Moving from Draft into the 5-stage pipeline
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'mov_link' => $validated['mov_link'] ?? null,
            ]);

            // 3. Process and attach the Tagging Module data
            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $tagData = [];
                    
                    // Map the frontend tag type to the correct PostgreSQL column
                    if ($tag['type'] === 'department') {
                        $tagData['tagged_department'] = $tag['value'];
                    } elseif ($tag['type'] === 'faculty') {
                        $tagData['collaborator_name'] = $tag['value'];
                    } elseif ($tag['type'] === 'campus') {
                        // Attempt to find the matching campus ID
                        $campus = Campus::where('name', 'ILIKE', '%' . $tag['value'] . '%')->first();
                        $tagData['tagged_campus_id'] = $campus ? $campus->id : null;
                    }

                    $deliverable->tags()->create($tagData);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Deliverable encoded and submitted successfully.',
                'deliverable' => $deliverable->load('tags')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while encoding the deliverable.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $deliverables = Deliverable::with('tags')->orderBy('created_at', 'desc')->get();
        return response()->json($deliverables);

        $query = Deliverable::with('tags')->orderBy('created_at', 'desc');

        // Scoped Visibility Based on Role Hierarchy
        if ($user->role === 'head') {
            $query->where('head_id', $user->id);
        } elseif ($user->role === 'director') {
            // Directors only see submissions from their specific campus
            $query->where('campus_id', $user->campus_id)
                  ->whereIn('status', ['submitted', 'director_endorsed', 'mov_upload', 'vp_approved']);
        } elseif ($user->role === 'vp_ories') {
            // VP sees university-wide submissions, primarily filtering for those needing final approval
            $query->whereIn('status', ['mov_upload', 'vp_approved']);
        }

        return response()->json($query->get(), 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:director_endorsed,mov_upload,vp_approved',
        ]);

        $deliverable = Deliverable::findOrFail($id);

        $deliverable->status = $request->status;
        
        // If an updated/additional link is passed, save it
        if ($request->has('mov_link')) {
            $deliverable->mov_link = $request->mov_link;
        }

        $deliverable->save();

        // State Machine Authorization
        if ($user->role === 'director' && $validated['status'] === 'director_endorsed') {
            $deliverable->update(['status' => 'director_endorsed']);
        } elseif ($user->role === 'head' && $validated['status'] === 'mov_upload') {
            $deliverable->update(['status' => 'mov_upload']);
        } elseif ($user->role === 'vp_ories' && $validated['status'] === 'vp_approved') {
            $deliverable->update(['status' => 'vp_approved']);
        } else {
            return response()->json(['message' => 'Unauthorized state transition.'], 403);
        }

        return response()->json([
            'message' => 'Deliverable status updated successfully.',
            'deliverable' => $deliverable
        ], 200);
    }

    public function show($id)
    {
        $deliverable = Deliverable::with('tags')->findOrFail($id);
        return response()->json($deliverable);
    }

    // Replace your current update method with this complete version
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
            'office_type' => 'required|string',
            'status' => 'required|string',
        ]);

        $deliverable = Deliverable::findOrFail($id);
        
        $deliverable->update([
            'title' => $request->title,
            'due_date' => $request->due_date,
            'office_type' => $request->office_type,
            'status' => $request->status,
        ]);

        // Fixes the 500 error: Safely delete old tags and recreate them
        if ($request->has('tags')) {
            $deliverable->tags()->delete(); // Wipe old tags
            
            foreach ($request->tags as $tag) {
                $tagData = ['deliverable_id' => $deliverable->id];
                
                // Map the frontend types to your exact database columns
                if ($tag['type'] === 'campus') {
                    $tagData['tagged_campus_id'] = (int) filter_var($tag['value'], FILTER_SANITIZE_NUMBER_INT) ?: null;
                } elseif ($tag['type'] === 'department') {
                    $tagData['tagged_department'] = $tag['value'];
                } elseif ($tag['type'] === 'faculty') {
                    $tagData['collaborator_name'] = $tag['value'];
                }

                \App\Models\DeliverableTag::create($tagData);
            }
        }

        return response()->json($deliverable);
    }
}