<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class VehicleLocationReport extends Model
{
    protected static $table = 'vehicle_location_reports';
    protected $fillable = [
        'id',
        'vehicle_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'battery_level',
        'route_id',
        'ride_request_id',
        'reported_at'
    ];

    protected $casts = [
        'vehicle_id' => 'integer',
        'route_id' => 'integer',
        'ride_request_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'speed_kmh' => 'float',
        'heading' => 'float',
        'battery_level' => 'integer'
    ];

    public function vehicle()
    {
        if (!isset($this->attributes['vehicle_id'])) {
            return null;
        }
        return Vehicle::find($this->attributes['vehicle_id']);
    }
}
