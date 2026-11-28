<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 'action', 'actor_id', 'actor_name', 'actor_role', 'occurred_at', 'late_minutes',
    ];

    public static function record(Trip $trip, User $actor, string $action, ?\DateTimeInterface $time = null, ?int $lateMinutes = null): void
    {
        self::create([
            'trip_id' => $trip->id,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'occurred_at' => \Carbon\Carbon::instance($time ?? now())->copy()->utc()->toDateTimeString(),
            'late_minutes' => $lateMinutes,
        ]);
    }
}
