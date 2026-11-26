<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TripDepartureMonitor
{
    public const GRACE_MINUTES = 5;

    public function deviceLabel(Trip $trip): string
    {
        $ids = $trip->vehicle_id
            ? Device::where('vehicle_id', $trip->vehicle_id)->orderBy('id')->pluck('device_id')->all()
            : [];

        return $ids ? implode(', ', $ids) : "Vehicle for trip #{$trip->id}";
    }

    public function check(): void
    {
        Trip::whereIn('status', ['approved', 'scheduled'])
            ->where(
                'scheduled_departure',
                '<=',
                now()->addMinutes(config('tracking.departure_warning_before_minutes'))
            )
            ->select('id')->chunkById(100, function ($trips) {
                foreach ($trips as $candidate) {
                    DB::transaction(function () use ($candidate) {
                        $trip = Trip::lockForUpdate()->find($candidate->id);
                        if (! $trip || ! in_array($trip->status, ['approved', 'scheduled'], true)) {
                            return;
                        }
                        $movement = $trip->movements()->where('movement_no', 1)->first();
                        if ($movement?->actual_departure_at) {
                            return;
                        }
                        $scheduled = $movement?->scheduled_departure
                            ?? Carbon::parse($trip->scheduled_departure);
                        $now = now();
                        $this->notifyUnreachableTrackersNearDeparture($trip, $scheduled, $now);

                        if ($scheduled->copy()->addMinutes(self::GRACE_MINUTES)->gt(now())) {
                            return;
                        }
                        $label = $this->deviceLabel($trip);
                        $schedule = $scheduled->copy()->timezone('Asia/Manila')->format('Y-m-d H:i');
                        $recipients = User::where('role', 'admin')
                            ->pluck('id')
                            ->push($trip->driver_id)
                            ->unique();
                        foreach ($recipients as $recipientId) {
                            $isDriver = (int) $recipientId === (int) $trip->driver_id;
                            Notification::firstOrCreate([
                                'event_key' => "departure-overdue:{$trip->id}:{$recipientId}:{$scheduled->timestamp}",
                            ], [
                                'user_id' => $recipientId,
                                'trip_id' => $trip->id,
                                'message' => $isDriver
                                    ? "Trip #{$trip->id} is five minutes past its scheduled departure and has not been started. Please update its status."
                                    : "{$label} hasn't started trip #{$trip->id} yet. Scheduled departure: {$schedule} PHT; the 5-minute grace period has passed.",
                                'priority' => 'normal',
                                'is_read' => false,
                            ]);
                        }
                    });
                }
            });
    }

    private function notifyUnreachableTrackersNearDeparture(
        Trip $trip,
        Carbon $scheduled,
        Carbon $now
    ): void {
        $warningMinutes = (int) config('tracking.departure_warning_before_minutes');
        if (
            $scheduled->lte($now) ||
            $scheduled->gt($now->copy()->addMinutes($warningMinutes))
        ) {
            return;
        }

        $devices = Device::where('vehicle_id', $trip->vehicle_id)->get();
        $unreachableDevices = $devices->filter(
            fn (Device $device) => $device->availability_status === 'unreachable'
        );
        if ($unreachableDevices->isEmpty()) {
            return;
        }

        $label = $unreachableDevices->pluck('device_id')->implode(', ');
        $schedule = $scheduled->copy()->timezone('Asia/Manila')->format('Y-m-d H:i');
        $recipients = User::where('role', 'admin')
            ->pluck('id')
            ->push($trip->driver_id)
            ->unique();

        foreach ($recipients as $recipientId) {
            Notification::firstOrCreate([
                'event_key' => "tracker-departure-unreachable:{$trip->id}:{$recipientId}:{$scheduled->timestamp}",
            ], [
                'user_id' => $recipientId,
                'trip_id' => $trip->id,
                'message' => "{$label} has no recent heartbeat before trip #{$trip->id}, scheduled for {$schedule} PHT. Check tracker connectivity before departure.",
                'priority' => 'normal',
                'is_read' => false,
            ]);
        }
    }
}
