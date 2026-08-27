<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'origin',
        'destination',
        'purpose',
        'scheduled_departure',
        'status',
        'total_distance',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function passengers()
    {
        return $this->hasMany(TripPassenger::class);
    }
}
