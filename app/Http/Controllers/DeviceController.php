<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'device-registry',
            'timestamp' => now(),
        ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:100',
            'online' => 'sometimes|boolean',
        ]);

        $query = Device::with('vehicle');

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($query) use ($search) {
                $query->where('device_id', 'like', "%{$search}%")
                    ->orWhere('vehicle_plate', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('online', $validated)) {
            $query->where('is_online', $request->boolean('online'));
        }

        return response()->json(
            $query->orderByDesc('registered_at')->paginate(20)
        );
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:100|unique:device_registry,device_id',
            'vehicle_id' => 'required|exists:vehicles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);

        $device = Device::create([
            'device_id' => $validated['device_id'],
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no,
            'registered_at' => now(),
            'is_online' => false,
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device' => $device,
        ], 201);
    }

    public function show(string $device_id)
    {
        $device = Device::with('vehicle')
            ->where('device_id', $device_id)
            ->firstOrFail();

        return response()->json($device);
    }

    public function update(Request $request, string $device_id)
    {
        $device = Device::where('device_id', $device_id)->firstOrFail();
        $validated = $request->validate([
            'vehicle_id' => 'sometimes|required|exists:vehicles,id',
        ]);

        if (array_key_exists('vehicle_id', $validated)) {
            $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
            $device->vehicle_id = $vehicle->id;
            $device->vehicle_plate = $vehicle->plate_no;
        }

        $device->save();

        return response()->json([
            'message' => 'Device updated successfully',
            'device' => $device,
        ]);
    }

    public function destroy(string $device_id)
    {
        $device = Device::where('device_id', $device_id)->firstOrFail();
        $device->delete();

        return response()->json([
            'message' => 'Device deleted successfully',
        ]);
    }

    public function byVehicle(int $vehicle_id)
    {
        $devices = Device::where('vehicle_id', $vehicle_id)->get();

        return response()->json($devices);
    }
}