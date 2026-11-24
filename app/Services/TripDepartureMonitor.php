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
            ->where('scheduled_departure', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->select('id')->chunkById(100, function ($trips) {
                foreach ($trips as $candidate) {
                    DB::transaction(function () use ($candidate) {
                        $trip = Trip::lockForUpdate()->find($candidate->id);
                        if (!$trip || !in_array($trip->status, ['approved', 'scheduled'], true)) {
                            return;
                        }
                        $movement = $trip->movements()->where('movement_no', 1)->first();
                        if ($movement?->actual_departure_at) {
                            return;
                        }
                        $scheduled = $movement?->scheduled_departure
                            ?? Carbon::parse($trip->scheduled_departure);
                        if ($scheduled->copy()->addMinutes(self::GRACE_MINUTES)->gt(now())) {
                            return;
                        }
                        $label = $this->deviceLabel($trip);
                        $schedule = $scheduled->copy()->timezone('Asia/Manila')->format('Y-m-d H:i');
                        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
                            Notification::firstOrCreate([
                                'event_key' => "departure-overdue:{$trip->id}:{$adminId}:{$scheduled->timestamp}",
                            ], [
                                'user_id' => $adminId,
                                'trip_id' => $trip->id,
                                'message' => "{$label} hasn't started trip #{$trip->id} yet. Scheduled departure: {$schedule} PHT; the 5-minute grace period has passed.",
                                'is_read' => false,
                            ]);
                        }
                    });
                }
            });
    }
}
