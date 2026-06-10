<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\NotificationManager;
use PDO;

class UserGroupPermissions extends Model
{
    protected static $table = 'user_group_permissions';

    protected $fillable = [
        'id',
        'group_id',
        'permission_id',
        'value',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'group_id' => 'integer',
        'permission_id' => 'integer',
        'value' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];


    public static function resetGroup(int $group_id)
    {
        $db = Database::getConnection();

        $sql = "DELETE FROM user_group_permissions WHERE group_id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$group_id]);

        return $stmt->fetch(\PDO::FETCH_OBJ);
    }
}
