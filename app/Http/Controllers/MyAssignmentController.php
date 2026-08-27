<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoordinatorAssignment;

class MyAssignmentController extends Controller
{
    public function show(Request $request)
    {
        $assignment = CoordinatorAssignment::with('vehicle')
            ->where('driver_id', $request->user()->id)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'No assignment found'], 404);
        }

        return response()->json($assignment);
    }
}
