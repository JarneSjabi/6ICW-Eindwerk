<?php

namespace App\Models;

use PDO;
use App\Core\Database;
use App\Core\Model;

class Vehicle extends Model
{
    protected static $table = 'vehicles';
    protected $fillable = [
        'id',
        'vehicle_template_id',
        'license_plate',
        'vin',
        'status',
        'current_latitude',
        'current_longitude',
        'battery_level',
        'odometer_km',
        'last_maintenance_date',
        'next_maintenance_km',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'vehicle_template_id' => 'integer',
        'battery_level' => 'integer',
        'odometer_km' => 'integer',
        'next_maintenance_km' => 'integer',
        'is_active' => 'boolean',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public static function getSearchableColumns(): array
    {
        return ['license_plate', 'vin'];
    }

    public static function getTotalCount($search = '')
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total FROM vehicles WHERE is_active = 1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (license_plate LIKE ? OR vin LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['total'];
    }

    protected static function getLatestLocationJoin(): string
    {
        return "LEFT JOIN (
                SELECT vehicle_id, latitude, longitude
                FROM vehicle_location_reports
                WHERE id IN (
                    SELECT MAX(id) FROM vehicle_location_reports GROUP BY vehicle_id
                )
            ) vlr ON v.id = vlr.vehicle_id";
    }

    public static function find($id)
    {
        $db = Database::getConnection();
        $sql = "SELECT v.*, vlr.latitude AS current_latitude, vlr.longitude AS current_longitude
                FROM vehicles v
                " . static::getLatestLocationJoin() . "
                WHERE v.id = ?
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);

        if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $model = new static($data);
            $model->exists = true;
            return $model;
        }

        return null;
    }

    public static function getPaginated($search = '', $page = 1, $limit = 20, $filters = [])
    {
        $db = Database::getConnection();
        $offset = ($page - 1) * $limit;
        $join = static::getLatestLocationJoin();

        $query = "SELECT v.*, vlr.latitude AS current_latitude, vlr.longitude AS current_longitude
                  FROM vehicles v
                  " . $join;
        $params = [];
        $whereAdded = false;

        if (!empty($search)) {
            $searchColumns = static::getSearchableColumns();
            if (!empty($searchColumns)) {
                $searchConditions = [];
                foreach ($searchColumns as $column) {
                    $searchConditions[] = "v.{$column} LIKE ?";
                    $params[] = "%{$search}%";
                }

                $query .= " WHERE (" . implode(' OR ', $searchConditions) . ")";
                $whereAdded = true;
            }
        }

        foreach ($filters as $column => $value) {
            if (!empty($value)) {
                $query .= $whereAdded ? " AND " : " WHERE ";
                $query .= "v.{$column} = ?";
                $params[] = $value;
                $whereAdded = true;
            }
        }

        $countQuery = "SELECT COUNT(*) as total FROM vehicles v";
        if ($whereAdded) {
            $countQuery .= " WHERE " . substr($query, strpos($query, 'WHERE ') + 6);
        }

        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['total'];

        $query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($data as $row) {
            $model = new static($row);
            $model->exists = true;
            $results[] = $model->toArray();
        }

        return [
            'records' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
            'search' => $search,
            'stats' => static::getDashboardStats(),
        ];
    }

    public function template()
    {
        if (!isset($this->attributes['vehicle_template_id'])) {
            return null;
        }
        return VehicleTemplate::find($this->attributes['vehicle_template_id']);
    }

    public function activeRides()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ride_requests WHERE vehicle_id = ? AND status IN ('assigned', 'in_progress')");
        $stmt->execute([$this->attributes['id'] ?? null]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function routes()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM routes WHERE vehicle_id = ? ORDER BY scheduled_start_time DESC");
        $stmt->execute([$this->attributes['id'] ?? null]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
