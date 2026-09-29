<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Rules\StrongPassword;
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
            'password' => ['required', 'string', 'min:8', new StrongPassword()],
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
            'password' => ['nullable', 'string', 'min:8', new StrongPassword()],
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
        // Generate secure random password (12 characters)
        // Format: Uppercase + lowercase + numbers + special chars
        $uppercase = chr(rand(65, 90)); // A-Z
        $lowercase = chr(rand(97, 122)); // a-z
        $number = rand(0, 9);
        $special = ['!', '@', '#', '$', '%', '^', '&', '*'][rand(0, 7)];
        
        // Generate additional random characters
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $randomPart = '';
        for ($i = 0; $i < 8; $i++) {
            $randomPart .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Combine and shuffle
        $newTempPassword = $uppercase . $lowercase . $number . $special . $randomPart;
        $newTempPassword = str_shuffle($newTempPassword);

        $driver->update([
            'password' => Hash::make($newTempPassword),
        ]);

        return response()->json([
            'message' => 'Password reset successfully. The driver must change this password on first login.',
            'temporary_password' => $newTempPassword,
            'note' => 'This temporary password is shown only once. Make sure to copy it.',
        ]);
    }
}
