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
        'return_scheduled_departure',
        'status',
        'total_distance',
    ];

    protected $appends = ['effective_status'];

    public function getEffectiveStatusAttribute(): string
    {
        if (in_array($this->status, ['pending', 'denied', 'completed'], true)) {
            return $this->status;
        }

        if ($this->status === 'approved' || $this->status === 'scheduled' || $this->status === 'active') {
            if ($this->status === 'active') {
                return 'active';
            }

            return 'scheduled';
        }

        return $this->status;
    }

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

    public function movements()
    {
        return $this->hasMany(TripMovement::class)->orderBy('movement_no');
    }
}
