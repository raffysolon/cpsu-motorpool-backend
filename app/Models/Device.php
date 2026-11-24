<?php

namespace App\Models;

use App\Services\TrackerAvailability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $table = 'device_registry';

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'vehicle_plate',
        'last_seen',
        'is_online',
        'registered_at',
    ];

    protected $hidden = [
        'tracker_key_hash',
    ];

    protected $appends = [
        'availability_status',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'registered_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getAvailabilityStatusAttribute(): string
    {
        $lastHeartbeatAt = $this->getAttributes()['last_heartbeat_at'] ?? null;

        return app(TrackerAvailability::class)->status(
            is_string($lastHeartbeatAt) ? $lastHeartbeatAt : null
        );
    }
}