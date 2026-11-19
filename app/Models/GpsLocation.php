<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsLocation extends Model
{
    use HasFactory;

    protected $table = 'gps_locations';

    protected $fillable = [
        'device_registry_id',
        'vehicle_id',
        'latitude',
        'longitude',
        'satellites',
        'recorded_at',
        'record_id',
        'device_sequence',
        'received_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'satellites' => 'integer',
        'device_sequence' => 'integer',
    ];

    public function getRecordedAtAttribute($value)
    {
        return $this->parseUtcTimestamp($value);
    }

    public function getReceivedAtAttribute($value)
    {
        return $this->parseUtcTimestamp($value);
    }

    public function getCreatedAtAttribute($value)
    {
        return $this->parseUtcTimestamp($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return $this->parseUtcTimestamp($value);
    }

    private function parseUtcTimestamp($value)
    {
        return $value === null
            ? null
            : Carbon::parse($value, 'UTC');
    }

    protected function serializeDate(\DateTimeInterface $date)
{
    return Carbon::instance($date)
        ->utc()
        ->format('Y-m-d\TH:i:s.u\Z');
}

protected function serializeDateTime($value)
{
    return $value;
}

public function toArray()
{
    $attributes = parent::toArray();

    foreach (['recorded_at', 'received_at', 'created_at', 'updated_at'] as $field) {
        $date = $this->getAttribute($field);

        $attributes[$field] = $date === null
            ? null
            : $date->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    return $attributes;
}

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_registry_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
