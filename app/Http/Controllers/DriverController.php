<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = User::where('role', 'driver')->get();
        return response()->json($drivers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'contact_number' => 'nullable|string',
            'license_number' => 'nullable|string',
            'password' => 'required|string|min:8',
        ]);

        $driver = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'driver',
        ]);

        return response()->json([
            'message' => 'Driver account created',
            'driver' => $driver,
        ], 201);
    }

    public function update(Request $request, User $driver)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $driver->id,
            'contact_number' => 'nullable|string',
            'license_number' => 'nullable|string',
            'password' => 'nullable|string|min:8',
        ]);

        $driver->name = $validated['name'];
        $driver->email = $validated['email'];
        $driver->contact_number = $validated['contact_number'] ?? null;
        $driver->license_number = $validated['license_number'] ?? null;

        if (!empty($validated['password'])) {
            $driver->password = Hash::make($validated['password']);
        }

        $driver->save();

        return response()->json(['message' => 'Driver updated', 'driver' => $driver]);
    }

    public function destroy(User $driver)
    {
        $driver->delete();
        return response()->json(['message' => 'Driver deleted']);
    }

    public function resetPassword(User $driver)
    {
        $newTempPassword = 'Driver@' . rand(1000, 9999);

        $driver->update([
            'password' => Hash::make($newTempPassword),
        ]);

        return response()->json([
            'message' => 'Password reset successfully',
            'temporary_password' => $newTempPassword,
        ]);
    }
}
