<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\CoordinatorAssignment;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $trips = Trip::with(['driver', 'vehicle', 'passengers'])
            ->latest()
            ->get();

        return response()->json($trips);
    }

    public function myTrips(Request $request)
    {
        $trips = Trip::with(['vehicle', 'passengers'])
            ->where('driver_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($trips);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin' => 'required|string',
            'destination' => 'required|string',
            'purpose' => 'required|string',
            'scheduled_departure' => 'required|date',
            'passengers' => 'nullable|array',
            'passengers.*.name' => 'required|string',
            'passengers.*.designation' => 'nullable|string',
        ]);

        $driver = $request->user();

        $assignment = CoordinatorAssignment::where('driver_id', $driver->id)->first();
        $vehicleId = $assignment ? $assignment->vehicle_id : null;

        $trip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleId,
            'origin' => $validated['origin'],
            'destination' => $validated['destination'],
            'purpose' => $validated['purpose'],
            'scheduled_departure' => $validated['scheduled_departure'],
            'status' => 'pending',
        ]);

        if (!empty($validated['passengers'])) {
            foreach ($validated['passengers'] as $passenger) {
                $trip->passengers()->create([
                    'name' => $passenger['name'],
                    'designation' => $passenger['designation'] ?? null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Trip request submitted',
            'trip' => $trip->load(['vehicle', 'passengers']),
        ], 201);
    }

    public function show(Trip $trip)
    {
        return response()->json($trip->load(['driver', 'vehicle', 'passengers']));
    }

    public function approve(Trip $trip)
    {
        $trip->update(['status' => 'approved']);
        return response()->json(['message' => 'Trip approved', 'trip' => $trip]);
    }

    public function deny(Trip $trip)
    {
        $trip->update(['status' => 'denied']);
        return response()->json(['message' => 'Trip denied', 'trip' => $trip]);
    }
}
