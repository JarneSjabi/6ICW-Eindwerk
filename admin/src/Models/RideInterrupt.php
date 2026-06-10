<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class RideInterrupt extends Model
{
    protected static $table = 'ride_interrupts';
    protected $fillable = [
        'id',
        'ride_request_id',
        'vehicle_id',
        'route_id',
        'interrupt_type',
        'description',
        'latitude',
        'longitude',
        'duration_minutes',
        'resolved',
        'resolved_at',
        'created_at'
    ];

    protected $casts = [
        'ride_request_id' => 'integer',
        'vehicle_id' => 'integer',
        'route_id' => 'integer',
        'duration_minutes' => 'integer',
        'resolved' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    public function rideRequest()
    {
        if (!isset($this->attributes['ride_request_id'])) {
            return null;
        }
        return RideRequest::find($this->attributes['ride_request_id']);
    }

    public function vehicle()
    {
        if (!isset($this->attributes['vehicle_id'])) {
            return null;
        }
        return Vehicle::find($this->attributes['vehicle_id']);
    }

    public function route()
    {
        if (!isset($this->attributes['route_id'])) {
            return null;
        }
        return Route::find($this->attributes['route_id']);
    }
}
