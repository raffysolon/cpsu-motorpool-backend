<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPassenger extends Model
{
    protected $fillable = [
        'trip_id',
        'name',
        'designation',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
