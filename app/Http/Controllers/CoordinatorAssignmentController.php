<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoordinatorAssignment;

class CoordinatorAssignmentController extends Controller
{
    public function index()
    {
        $assignments = CoordinatorAssignment::with(['driver', 'vehicle'])->get();
        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campus_name' => 'required|string',
            'coordinator_name' => 'required|string',
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        $assignment = CoordinatorAssignment::create($validated);

        return response()->json([
            'message' => 'Assignment created',
            'assignment' => $assignment,
        ], 201);
    }

    public function update(Request $request, CoordinatorAssignment $assignment)
    {
        $validated = $request->validate([
            'campus_name' => 'required|string',
            'coordinator_name' => 'required|string',
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        $assignment->update($validated);

        return response()->json(['message' => 'Assignment updated', 'assignment' => $assignment]);
    }

    public function destroy(CoordinatorAssignment $assignment)
    {
        $assignment->delete();
        return response()->json(['message' => 'Assignment deleted']);
    }
}
