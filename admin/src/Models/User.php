<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\NotificationManager;
use PDO;

class User extends Model
{
    protected static $table = 'users';

    protected $fillable = [
        'id',
        'firstname',
        'lastname',
        'email',
        'password_hash',
        'remember_token',
        'user_group_id',
        'is_root_user',
        'last_login',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'user_group_id' => 'integer',
        'is_active' => 'boolean',
        'last_login' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    protected $hidden = [
        'password_hash',
        'remember_token'
    ];

    
    public static function getSearchableColumns(): array
    {
        return ['firstname', 'lastname', 'email'];
    }

    public function findByUsername(string $username)
    {
        $stmt = Database::getConnection()->prepare("
            SELECT * FROM users 
            WHERE LOWER(CONCAT(firstname, ' ', lastname)) = LOWER(?) 
            OR LOWER(CONCAT(firstname, lastname)) = LOWER(?) 
            OR LOWER(email) = LOWER(?) 
            LIMIT 1
        ");
        $stmt->execute([$username, $username, $username]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $this->fill($data);
            $this->exists = true;
            return $data;
        }

        return null;
    }

    public function getGroup()
    {
        $stmt = Database::getConnection()->prepare("
            SELECT g.* 
            FROM user_groups g 
            JOIN users u ON u.user_group_id = g.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$this->attributes['id']]);
        return $stmt->fetch();
    }

    public function hasPermission(string $permission): bool
    {
        $stmt = Database::getConnection()->prepare("
            SELECT gp.value 
            FROM user_group_permissions gp
            JOIN users u ON u.user_group_id = gp.group_id
            JOIN permissions p ON p.id = gp.permission_id
            WHERE u.id = ? AND p.name = ?
        ");
        $stmt->execute([$this->attributes['id'], $permission]);
        $result = $stmt->fetch();
        return $result ? (bool)$result['value'] : false;
    }

    public function updateLastLogin()
    {
        $stmt = Database::getConnection()->prepare("
            UPDATE users 
            SET last_login = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$this->attributes['id']]);
    }

    public function getUserGreeting()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("  SELECT u.firstname, u.lastname, ug.name as group_name, ug.id as group_id
            FROM users u
            LEFT JOIN user_groups ug ON u.user_group_id = ug.id
            WHERE u.id = ?
            LIMIT 1");
        $stmt->execute([$this->attributes['id']]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $greeting = "Welkom terug, " . htmlspecialchars($user['firstname']) . " " .
                htmlspecialchars($user['lastname']) . "!";
            if ($user['group_name']) {
                $greeting .= " Je bent ingelogd als " . htmlspecialchars($user['group_name']) . ".";
            }

    
            return $greeting;
        }
        return "Welkom!"; 
    }

    
    public static function getDashboardStats()
    {
        $db = Database::getConnection();

        $sql = "SELECT 
    (SELECT COUNT(*) FROM users) AS user_count,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) AS active_user_count
";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_OBJ);
    }
}
