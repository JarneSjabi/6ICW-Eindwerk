<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class RideRequest extends Model
{
    protected static $table = 'ride_requests';
    protected $fillable = [
        'id',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'dropoff_address',
        'requested_pickup_time',
        'passenger_count',
        'comfort_level',
        'shared_ride',
        'estimated_distance_km',
        'estimated_duration_minutes',
        'estimated_price_cents',
        'status',
        'vehicle_id',
        'route_id',
        'actual_pickup_time',
        'actual_dropoff_time',
        'actual_distance_km',
        'actual_duration_minutes',
        'actual_price_cents',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'vehicle_id' => 'integer',
        'route_id' => 'integer',
        'passenger_count' => 'integer',
        'shared_ride' => 'boolean',
        'estimated_distance_km' => 'float',
        'estimated_duration_minutes' => 'integer',
        'estimated_price_cents' => 'integer',
        'actual_distance_km' => 'float',
        'actual_duration_minutes' => 'integer',
        'actual_price_cents' => 'integer',
        'pickup_latitude' => 'float',
        'pickup_longitude' => 'float',
        'dropoff_latitude' => 'float',
        'dropoff_longitude' => 'float',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public static function getSearchableColumns(): array
    {
        return ['customer_name', 'customer_email', 'customer_phone', 'pickup_address', 'dropoff_address'];
    }

    public static function getTotalCount($search = '')
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total FROM ride_requests WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (customer_name LIKE ? OR customer_email LIKE ? OR pickup_address LIKE ? OR dropoff_address LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['total'];
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

    public function user()
    {
        if (!isset($this->attributes['user_id'])) {
            return null;
        }
        return User::find($this->attributes['user_id']);
    }
}
