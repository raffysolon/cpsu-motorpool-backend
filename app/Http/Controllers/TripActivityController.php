<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripActivity;
use Carbon\Carbon;

class TripActivityController extends Controller
{
    public function index(Trip $trip)
    {
        $events = TripActivity::where('trip_id', $trip->id)->orderBy('id')->get()
            ->mapWithKeys(fn (TripActivity $event) => [$event->action => [
                'action' => $event->action,
                'actor_name' => $event->actor_name,
                'actor_role' => $event->actor_role,
                'occurred_at' => Carbon::parse($event->occurred_at, 'UTC')->toISOString(),
                'late_minutes' => $event->late_minutes,
            ]]);

        $addHistorical = function (string $action, ?Carbon $time, ?string $role = null, ?int $lateMinutes = null) use ($events): void {
            if ($time === null || $events->has($action)) {
                return;
            }
            $events->put($action, [
                'action' => $action,
                'actor_name' => null,
                'actor_role' => $role,
                'occurred_at' => $time->toISOString(),
                'late_minutes' => $lateMinutes,
            ]);
        };

        $source = $trip->source;
        $creation = match ($source) {
            'admin' => 'admin_created',
            'driver' => 'submitted',
            default => 'created',
        };
        $addHistorical($creation, $trip->created_at, in_array($source, ['admin', 'driver'], true) ? $source : null);

        foreach ($trip->movements as $movement) {
            if ((int) $movement->movement_no === 1) {
                $addHistorical('outbound_started', $movement->actual_departure_at, null, $movement->late_minutes);
                $addHistorical('outbound_arrived', $movement->actual_arrival_at);
            }
            if ((int) $movement->movement_no === 2) {
                $addHistorical('return_started', $movement->actual_departure_at, null, $movement->late_minutes);
                if ($trip->status === 'completed') {
                    $addHistorical('completed', $movement->actual_arrival_at);
                }
            }
        }

        $order = array_flip(['created', 'submitted', 'admin_created', 'approved', 'denied',
            'outbound_started', 'outbound_arrived', 'return_started', 'completed']);
        $timeline = $events->sort(fn (array $a, array $b) =>
            ($a['occurred_at'] <=> $b['occurred_at']) ?: ($order[$a['action']] <=> $order[$b['action']])
        )->values();

        return response()->json([
            'events' => $timeline,
            'decision_time_missing' => $source === 'driver'
                && $trip->status !== 'pending'
                && !$events->has('approved') && !$events->has('denied'),
        ]);
    }
}
