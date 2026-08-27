<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinatorAssignment extends Model
{
    protected $table = 'campus_coordinator_assignments';

    protected $fillable = [
        'campus_name',
        'coordinator_name',
        'driver_id',
        'vehicle_id',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
