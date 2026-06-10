<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\NotificationManager;
use PDO;

class UserGroup extends Model
{
    protected static $table = 'user_groups';

    protected $fillable = [
        'id',
        'name',
        'description',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    
    public function canDelete(): bool
    {
        return !$this->hasUsers() || $this->id == 1;
    }

    
    public function hasUsers()
    {
        return $this->getUserCount() > 0;
    }

    
    public function getDeleteErrorMessage(): string
    {
        return 'Kan rol niet verwijderen omdat er nog gebruikers aan gekoppeld zijn of omdat dit de standaardrol is';
    }

    
    public function getUserCount()
    {
        $db = Database::getConnection();

        $sql = "SELECT COUNT(*) AS total_users
                    FROM users 
                    WHERE user_group_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$this->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_users = $row ? (int)$row['total_users'] : 0;
        return $total_users ?? 0;
    }

    
    public static function getDashboardStats()
    {
        $db = Database::getConnection();

        $sql = "SELECT 
    (SELECT COUNT(*) FROM user_groups) AS user_groups_count,
    (SELECT COUNT(*) FROM user_groups WHERE is_active = 1) AS active_user_groups_count
";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_OBJ);
    }
}
