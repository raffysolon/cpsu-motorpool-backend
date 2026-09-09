<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripMovement extends Model
{
    protected $fillable = [
        'trip_id',
        'movement_no',
        'origin',
        'destination',
        'scheduled_departure',
        'actual_departure_at',
        'actual_arrival_at',
        'departure_latitude',
        'departure_longitude',
        'arrival_latitude',
        'arrival_longitude',
        'status',
    ];

    protected $casts = [
        'scheduled_departure' => 'datetime',
        'actual_departure_at' => 'datetime',
        'actual_arrival_at' => 'datetime',
        'departure_latitude' => 'float',
        'departure_longitude' => 'float',
        'arrival_latitude' => 'float',
        'arrival_longitude' => 'float',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
