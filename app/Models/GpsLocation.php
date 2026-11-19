<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsLocation extends Model
{
    use HasFactory;

    protected $table = 'gps_locations';

    protected $fillable = [
        'device_registry_id', 'vehicle_id', 'latitude', 'longitude',
        'satellites', 'recorded_at', 'record_id', 'device_sequence', 'received_at',
    ];

    protected $casts = [
        'latitude' => 'float', 'longitude' => 'float',
        'satellites' => 'integer', 'recorded_at' => 'datetime',
        'received_at' => 'datetime', 'device_sequence' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_registry_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
