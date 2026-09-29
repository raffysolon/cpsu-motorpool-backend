<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Helpers\InputSanitizer;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::all();
        return response()->json($vehicles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'plate_no' => 'required|string|unique:vehicles,plate_no',
            'status' => 'nullable|string',
        ]);

        $vehicle = Vehicle::create([
            'name' => InputSanitizer::clean($validated['name']),
            'plate_no' => InputSanitizer::clean($validated['plate_no']),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Vehicle added',
            'vehicle' => $vehicle,
        ], 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'plate_no' => 'required|string|unique:vehicles,plate_no,' . $vehicle->id,
            'status' => 'nullable|string',
        ]);

        $vehicle->update([
            'name' => InputSanitizer::clean($validated['name']),
            'plate_no' => InputSanitizer::clean($validated['plate_no']),
            'status' => $validated['status'] ?? $vehicle->status,
        ]);

        return response()->json(['message' => 'Vehicle updated', 'vehicle' => $vehicle]);
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return response()->json(['message' => 'Vehicle deleted']);
    }
}
