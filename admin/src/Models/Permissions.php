<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\NotificationManager;
use PDO;

class Permissions extends Model
{
    protected static $table = 'permissions';

    protected $fillable = [
        'id',
        'name',
        'description',
        'category',
        'risk_grade',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'risk_grade' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    
    public function getAllPermissions()
    {
        $stmt = Database::getConnection()->prepare("SELECT * FROM permissions ORDER BY category, name");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    
    public function getGroupPermissions(int $user_group_id)
    {
        $stmt = Database::getConnection()->prepare("SELECT p.*, rp.value 
                              FROM permissions p
                              LEFT JOIN user_group_permissions rp ON rp.permission_id = p.id AND rp.group_id = ?
                              ORDER BY p.category, p.name");
        $stmt->execute([$user_group_id]);
        return $stmt->fetchAll();
    }
}
