<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Route extends Model
{
    protected static $table = 'routes';
    protected $fillable = [
        'id',
        'vehicle_id',
        'name',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'waypoints',
        'distance_km',
        'estimated_duration_minutes',
        'traffic_factor',
        'status',
        'scheduled_start_time',
        'actual_start_time',
        'actual_end_time',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'vehicle_id' => 'integer',
        'distance_km' => 'float',
        'estimated_duration_minutes' => 'integer',
        'traffic_factor' => 'float',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
        'waypoints' => 'json',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public static function getSearchableColumns(): array
    {
        return ['name'];
    }

    public static function getTotalCount($search = '')
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total FROM routes WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND name LIKE ?";
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
}
