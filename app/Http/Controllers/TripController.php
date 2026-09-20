<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\CoordinatorAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class TripController extends Controller
{
    private function conflictingResourceIds(string $resourceKey, ?string $scheduledDeparture = null)
    {
        $selectedDate = $scheduledDeparture ? Carbon::parse($scheduledDeparture) : null;

        $resourceIds = Trip::query()
            ->whereNotIn('status', ['denied', 'completed'])
            ->get()
            ->filter(function ($trip) use ($selectedDate) {
                if ($selectedDate === null) {
                    return in_array($trip->status, ['approved', 'active'], true);
                }

                $tripDate = Carbon::parse($trip->scheduled_departure);
                $sameSchedule = in_array($trip->status, ['approved', 'active'], true)
                    && $tripDate->format('Y-m-d H:i:s') === $selectedDate->format('Y-m-d H:i:s');
                $sameSlot = in_array($trip->status, ['approved', 'active'], true)
                    && $tripDate->isSameDay($selectedDate)
                    && $tripDate->hour === $selectedDate->hour
                    && $tripDate->minute === $selectedDate->minute;

                return $sameSchedule || $sameSlot;
            })
            ->pluck($resourceKey)
            ->filter(fn ($id) => !empty($id))
            ->unique();

        return $resourceIds;
    }

    private function hasResourceConflict(string $resourceKey, int $resourceId, ?string $scheduledDeparture = null, ?int $excludeTripId = null): bool
    {
        $selectedDate = $scheduledDeparture ? Carbon::parse($scheduledDeparture) : null;

        $conflictingTrips = Trip::query()
            ->where($resourceKey, $resourceId)
            ->whereNotIn('status', ['denied', 'completed'])
            ->when($excludeTripId, fn ($query) => $query->whereKeyNot($excludeTripId))
            ->get();

        foreach ($conflictingTrips as $trip) {
            if ($selectedDate === null) {
                if (in_array($trip->status, ['approved', 'active'], true)) {
                    return true;
                }
                continue;
            }

            $tripDate = Carbon::parse($trip->scheduled_departure);
            $sameSchedule = in_array($trip->status, ['approved', 'active'], true)
                && $tripDate->format('Y-m-d H:i:s') === $selectedDate->format('Y-m-d H:i:s');
            $sameSlot = in_array($trip->status, ['approved', 'active'], true)
                && $tripDate->isSameDay($selectedDate)
                && $tripDate->hour === $selectedDate->hour
                && $tripDate->minute === $selectedDate->minute;

            if ($sameSchedule || $sameSlot) {
                return true;
            }
        }

        return false;
    }

    private function createViceVersaMovements(Trip $trip, ?string $returnScheduledDeparture = null): void
    {
        if ($trip->movements()->exists()) {
            return;
        }

        $trip->movements()->createMany([
            [
                'movement_no' => 1,
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'scheduled_departure' => $trip->scheduled_departure,
                'status' => 'scheduled',
            ],
            [
                'movement_no' => 2,
                'origin' => $trip->destination,
                'destination' => $trip->origin,
                'scheduled_departure' => $returnScheduledDeparture,
                'status' => 'scheduled',
            ],
        ]);
    }

    private function ensureMovements(Trip $trip): void
    {
        $returnSchedule = $trip->return_scheduled_departure
            ? Carbon::parse($trip->return_scheduled_departure)->toDateTimeString()
            : null;

        $this->createViceVersaMovements($trip, $returnSchedule);
    }

    private function validateLocation(Request $request): array
    {
        return $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }

    private function authorizeDriverTrip(Request $request, Trip $trip): void
    {
        abort_unless(
            strtolower((string) $request->user()->role) === 'driver'
                && (int) $trip->driver_id === (int) $request->user()->id,
            403,
            'Only the assigned driver can record trip movement events.'
        );
    }
    public function index(Request $request)
    {
        $trips = Trip::with(['driver', 'vehicle', 'passengers', 'movements'])
            ->latest()
            ->get();

        $trips->each(fn (Trip $trip) => $this->ensureMovements($trip));

        return response()->json($trips);
    }

    public function myTrips(Request $request)
    {
        $status = strtolower((string) $request->query('status', ''));
        $today = now()->startOfDay();

        $query = Trip::with(['vehicle', 'passengers', 'movements'])
            ->where('driver_id', $request->user()->id);

        if ($status !== '') {
            if ($status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($status === 'approved') {
                $query->where(function ($q) {
                    $q->where('status', 'approved');
                });
            } elseif ($status === 'scheduled') {
                $query->where('status', 'approved');
            } elseif ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status === 'denied') {
                $query->where('status', 'denied');
            } elseif ($status === 'completed') {
                $query->where('status', 'completed');
            }
        }

        $trips = $query->latest()->get();

        $trips->each(fn (Trip $trip) => $this->ensureMovements($trip));

        return response()->json($trips->map(function ($trip) {
            $trip->effective_status = $trip->effective_status;
            return $trip;
        }));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin' => 'required|string',
            'destination' => 'required|string',
            'purpose' => 'required|string',
            'scheduled_departure' => 'required|date',
            'return_scheduled_departure' => 'nullable|date',
            'passengers' => 'nullable|array',
            'passengers.*.name' => 'required|string',
            'passengers.*.designation' => 'nullable|string',
        ]);

        $driver = $request->user();

        $assignment = CoordinatorAssignment::with('vehicle')
            ->where('driver_id', $driver->id)
            ->first();

        $vehicleId = $assignment?->vehicle_id;

        $trip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleId,
            'origin' => $validated['origin'],
            'destination' => $validated['destination'],
            'purpose' => $validated['purpose'],
            'scheduled_departure' => $validated['scheduled_departure'],
            'return_scheduled_departure' => $validated['return_scheduled_departure'] ?? null,
            'status' => 'pending',
        ]);

        $this->createViceVersaMovements($trip, $validated['return_scheduled_departure'] ?? null);

        if (!empty($validated['passengers'])) {
            foreach ($validated['passengers'] as $passenger) {
                $trip->passengers()->create([
                    'name' => $passenger['name'],
                    'designation' => $passenger['designation'] ?? null,
                ]);
            }
        }

        $admins = User::where('role', 'admin')->get();
        $driverName = $driver->name ?: 'Driver';

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'trip_id' => $trip->id,
                'message' => "New trip request from {$driverName} to {$trip->destination}",
                'is_read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Trip request submitted',
            'trip' => $trip->load(['vehicle', 'passengers', 'movements']),
        ], 201);
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'origin' => 'nullable|string',
            'destination' => 'nullable|string',
            'purpose' => 'nullable|string',
            'scheduled_departure' => ['nullable', 'date'],
            'return_scheduled_departure' => ['nullable', 'date'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'passengers' => 'nullable|array',
            'passengers.*.name' => 'required|string',
            'passengers.*.designation' => 'nullable|string',
        ]);

        $origin = trim((string) ($validated['origin'] ?? '')) ?: 'TBD';
        $destination = trim((string) ($validated['destination'] ?? '')) ?: 'TBD';
        $purpose = trim((string) ($validated['purpose'] ?? '')) ?: 'Not specified';
        $scheduledDeparture = $validated['scheduled_departure'] ?? null;
        $scheduledDepartureForStorage = $scheduledDeparture ?: now()->toDateTimeString();

        if ($scheduledDeparture) {
            $driverConflict = $this->hasResourceConflict('driver_id', (int) $validated['driver_id'], $scheduledDeparture);
            $vehicleConflict = $this->hasResourceConflict('vehicle_id', (int) $validated['vehicle_id'], $scheduledDeparture);

            if ($driverConflict || $vehicleConflict) {
                return response()->json([
                    'message' => 'This driver or vehicle is already assigned to a conflicting trip during the selected schedule.',
                ], 422);
            }
        }

        $trip = Trip::create([
            'driver_id' => $validated['driver_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'origin' => $origin,
            'destination' => $destination,
            'purpose' => $purpose,
            'scheduled_departure' => $scheduledDepartureForStorage,
            'return_scheduled_departure' => $validated['return_scheduled_departure'] ?? null,
            'status' => 'approved',
        ]);

        $this->createViceVersaMovements($trip, $validated['return_scheduled_departure'] ?? null);

        if (!empty($validated['passengers'])) {
            foreach ($validated['passengers'] as $passenger) {
                $trip->passengers()->create([
                    'name' => $passenger['name'],
                    'designation' => $passenger['designation'] ?? null,
                ]);
            }
        }

        $driver = User::find($validated['driver_id']);

        if ($driver) {
            Notification::create([
                'user_id' => $driver->id,
                'trip_id' => $trip->id,
                'message' => "A new trip to {$trip->destination} has been created and assigned to you",
                'is_read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Trip request submitted',
            'trip' => $trip->load(['driver', 'vehicle', 'passengers', 'movements']),
        ], 201);
    }

    public function availableDrivers(Request $request)
    {
        $scheduledDeparture = $request->query('scheduled_departure');
        $occupiedDriverIds = $scheduledDeparture
            ? $this->conflictingResourceIds('driver_id', $scheduledDeparture)
            : collect();

        $assignedDriverIds = CoordinatorAssignment::pluck('driver_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $drivers = User::where('role', 'driver')
            ->whereNotNull('id')
            ->when($assignedDriverIds !== [], function ($query) use ($assignedDriverIds) {
                $query->whereNotIn('id', $assignedDriverIds);
            })
            ->when($occupiedDriverIds->isNotEmpty(), function ($query) use ($occupiedDriverIds) {
                $query->whereNotIn('id', $occupiedDriverIds);
            })
            ->get()
            ->map(function ($driver) {
                $driver->coordinator_assignment = CoordinatorAssignment::where('driver_id', $driver->id)->first();
                return $driver;
            });

        return response()->json($drivers);
    }

    public function availableVehicles(Request $request)
    {
        $scheduledDeparture = $request->query('scheduled_departure');
        $occupiedVehicleIds = $scheduledDeparture
            ? $this->conflictingResourceIds('vehicle_id', $scheduledDeparture)
            : collect();

        $assignedVehicleIds = CoordinatorAssignment::pluck('vehicle_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $vehicles = Vehicle::query()
            ->whereNotNull('id')
            ->when($assignedVehicleIds !== [], function ($query) use ($assignedVehicleIds) {
                $query->whereNotIn('id', $assignedVehicleIds);
            })
            ->when($occupiedVehicleIds->isNotEmpty(), function ($query) use ($occupiedVehicleIds) {
                $query->whereNotIn('id', $occupiedVehicleIds);
            })
            ->get()
            ->map(function ($vehicle) {
                $vehicle->coordinator_assignment = CoordinatorAssignment::where('vehicle_id', $vehicle->id)->first();
                return $vehicle;
            });

        return response()->json($vehicles);
    }

    public function show(Trip $trip)
    {
        $this->ensureMovements($trip);

        return response()->json($trip->load(['driver', 'vehicle', 'passengers', 'movements']));
    }

    public function update(Request $request, Trip $trip)
    {
        $user = $request->user();

        if ($user && strtolower((string) $user->role) === 'driver' && (int) $trip->driver_id !== (int) $user->id) {
            abort(403, 'You are not authorized to update this trip ticket.');
        }

        $validated = $request->validate([
            'departure_date' => ['sometimes', 'date'],
            'departure_time' => ['sometimes', 'date_format:H:i'],
            'departure_place' => ['sometimes', 'string'],
            'origin' => ['sometimes', 'string'],
            'scheduled_departure' => ['sometimes', 'date'],
            'return_scheduled_departure' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('departure_place', $validated)) {
            $validated['origin'] = $validated['departure_place'];
            unset($validated['departure_place']);
        }

        if (array_key_exists('departure_date', $validated) || array_key_exists('departure_time', $validated)) {
            $departure = Carbon::parse($trip->scheduled_departure);
            $date = $validated['departure_date'] ?? $departure->format('Y-m-d');
            $time = $validated['departure_time'] ?? $departure->format('H:i');
            $validated['scheduled_departure'] = Carbon::parse("{$date} {$time}");
            unset($validated['departure_date'], $validated['departure_time']);
        }

        $trip->update($validated);

        return response()->json([
            'message' => 'Trip ticket updated',
            'trip' => $trip->fresh()->load(['driver', 'vehicle', 'passengers', 'movements']),
        ]);
    }

    public function start(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($request, $trip);
        $location = $this->validateLocation($request);
        $this->ensureMovements($trip);
        $movement = $trip->movements()->where('movement_no', 1)->firstOrFail();
        $now = now();

        abort_unless(
            in_array($trip->status, ['approved', 'scheduled'], true)
                && $movement->status === 'scheduled'
                && $movement->scheduled_departure
                && $movement->scheduled_departure->isSameDay($now)
                && $movement->scheduled_departure->copy()->subMinutes(10)->lte($now),
            422,
            'This trip can start up to 10 minutes before its scheduled departure time.'
        );

        $movement->update([
            'actual_departure_at' => $now,
            'departure_latitude' => $location['latitude'] ?? null,
            'departure_longitude' => $location['longitude'] ?? null,
            'status' => 'active',
        ]);
        $trip->update(['status' => 'active']);

        $startedEarly = $now->lt($movement->scheduled_departure);
        $startedLate = $now->gt($movement->scheduled_departure);
        $startTiming = 'on_time';
        $message = 'Trip started on time.';

        if ($startedEarly) {
            $startTiming = 'early';
            $message = 'Trip started 10 minutes before the scheduled departure time.';
        } elseif ($startedLate) {
            $startTiming = 'late';
            $message = 'Trip started late after the scheduled departure time.';
        }

        $driverName = $request->user()->name ?: 'Driver';
        $adminMessage = match ($startTiming) {
            'early' => "{$driverName} started the trip early, 10 minutes before the scheduled departure.",
            'late' => "{$driverName} started the trip late after the scheduled departure.",
            default => "{$driverName} started the trip on time.",
        };

        foreach (User::where('role', 'admin')->get() as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'trip_id' => $trip->id,
                'message' => $adminMessage,
                'is_read' => false,
            ]);
        }

        return response()->json([
            'message' => $message,
            'start_timing' => $startTiming,
            'trip' => $trip->fresh()->load('movements'),
        ]);
    }

    public function end(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($request, $trip);
        $location = $this->validateLocation($request);
        $this->ensureMovements($trip);
        $movement = $trip->movements()->where('movement_no', 1)->firstOrFail();

        abort_unless($movement->status === 'active', 422, 'The outbound trip is not active.');

        $movement->update([
            'actual_arrival_at' => now(),
            'arrival_latitude' => $location['latitude'] ?? null,
            'arrival_longitude' => $location['longitude'] ?? null,
            'status' => 'completed',
        ]);

        return response()->json(['message' => 'Outbound trip ended', 'trip' => $trip->fresh()->load('movements')]);
    }

    public function startReturn(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($request, $trip);
        $location = $this->validateLocation($request);
        $this->ensureMovements($trip);
        $outbound = $trip->movements()->where('movement_no', 1)->firstOrFail();
        $return = $trip->movements()->where('movement_no', 2)->firstOrFail();

        abort_unless($outbound->status === 'completed' && $return->status === 'scheduled', 422, 'The return trip is not ready to start.');

        $return->update([
            'actual_departure_at' => now(),
            'departure_latitude' => $location['latitude'] ?? null,
            'departure_longitude' => $location['longitude'] ?? null,
            'status' => 'active',
        ]);

        return response()->json(['message' => 'Return trip started', 'trip' => $trip->fresh()->load('movements')]);
    }

    public function endReturn(Request $request, Trip $trip)
    {
        $this->authorizeDriverTrip($request, $trip);
        $location = $this->validateLocation($request);
        $this->ensureMovements($trip);
        $return = $trip->movements()->where('movement_no', 2)->firstOrFail();

        abort_unless($return->status === 'active', 422, 'The return trip is not active.');

        $return->update([
            'actual_arrival_at' => now(),
            'arrival_latitude' => $location['latitude'] ?? null,
            'arrival_longitude' => $location['longitude'] ?? null,
            'status' => 'completed',
        ]);
        $trip->update(['status' => 'completed']);

        return response()->json(['message' => 'Return trip ended', 'trip' => $trip->fresh()->load('movements')]);
    }

    public function approve(Trip $trip)
    {
        $this->ensureMovements($trip);
        $trip->update(['status' => 'approved']);
        $trip->refresh();
        $trip->effective_status = $trip->effective_status;

        Notification::create([
            'user_id' => $trip->driver_id,
            'trip_id' => $trip->id,
            'message' => "Your trip request to {$trip->destination} has been approved",
            'is_read' => false,
        ]);

        return response()->json(['message' => 'Trip approved', 'trip' => $trip]);
    }

    public function deny(Trip $trip)
    {
        $trip->update(['status' => 'denied']);
        return response()->json(['message' => 'Trip denied', 'trip' => $trip]);
    }

    public function print(Request $request, Trip $trip)
    {
        $user = $request->user();
        $role = $user ? strtolower((string) $user->role) : '';

        if ($user && $role === 'driver') {
            if ((int) $trip->driver_id !== (int) $user->id) {
                abort(403, 'You are not authorized to print this trip ticket.');
            }

            if (!in_array($trip->status, ['approved', 'scheduled', 'active', 'completed'], true)) {
                abort(403, 'Only approved, scheduled, active, or completed trips can be printed by the driver.');
            }
        }

        if ($user && in_array($role, ['admin', 'coordinator'], true)) {
            // Admin/coordinator can preview any trip ticket for review and approval flow.
        }

        $this->ensureMovements($trip);
        $trip->load(['driver', 'vehicle', 'passengers', 'movements']);
        set_time_limit(120);
        $pdf = Pdf::loadView('trip_ticket', ['trip' => $trip])
            ->setOptions(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => false]);
        $filename = 'trip_ticket_' . $trip->id . '.pdf';
        $path = storage_path('app/public/pdfs/' . $filename);
        File::ensureDirectoryExists(dirname($path));
        $pdf->save($path);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'PDF generated successfully',
                'filename' => $filename,
                'path' => $path,
                'url' => asset('storage/pdfs/' . $filename),
            ]);
        }

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function previewPdf(Request $request)
    {
        $trip = new Trip([
            'id' => 1,
            'driver_id' => 1,
            'vehicle_id' => 1,
            'destination' => 'Manila',
            'purpose' => 'Official Business',
            'scheduled_departure' => now(),
        ]);

        $trip->setRelation('driver', new \App\Models\User([
            'id' => 1,
            'name' => 'Sample Driver',
        ]));

        $trip->setRelation('vehicle', new \App\Models\Vehicle([
            'id' => 1,
            'name' => 'Sample Vehicle',
            'plate_no' => 'ABC-1234',
        ]));

        $trip->setRelation('passengers', collect([
            new \App\Models\TripPassenger(['name' => 'John Doe']),
            new \App\Models\TripPassenger(['name' => 'Jane Smith']),
        ]));

        set_time_limit(120);
        $pdf = Pdf::loadView('trip_ticket', ['trip' => $trip])
            ->setOptions(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => false]);
        $filename = 'trip_ticket_preview.pdf';
        $path = storage_path('app/public/pdfs/' . $filename);
        File::ensureDirectoryExists(dirname($path));
        $pdf->save($path);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Preview PDF generated successfully',
                'filename' => $filename,
                'full_path' => $path,
                'access_url' => asset('storage/pdfs/' . $filename),
            ]);
        }

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
