<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class VehicleTemplate extends Model
{
    protected static $table = 'vehicle_templates';
    protected $fillable = [
        'id',
        'name',
        'brand',
        'model',
        'capacity',
        'max_range_km',
        'features',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'max_range_km' => 'integer',
        'is_active' => 'boolean',
        'features' => 'json',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public static function getSearchableColumns(): array
    {
        return ['name', 'description'];
    }

    public static function getTotalCount($search = '')
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as total FROM vehicle_templates WHERE is_active = 1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['total'];
    }

    public function vehicles()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE vehicle_template_id = ?");
        $stmt->execute([$this->attributes['id'] ?? null]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
