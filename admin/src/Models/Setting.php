<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Setting extends Model
{
    protected static $table = 'settings';
    protected $fillable = [
        'id',
        'key_name',
        'value',
        'type',
        'description',
        'category',
        'updated_at'
    ];

    protected $casts = [
        'updated_at' => 'timestamp'
    ];

    
    public static function getValue($key, $default = null)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT value, type FROM settings WHERE `key_name` = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        
        $value = $result['value'];
        switch ($result['type']) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    
    public static function set($key, $value, $type = 'string', $description = null, $category = 'general')
    {
        $db = Database::getConnection();
        
        
        if ($type === 'json') {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } else {
            $value = (string)$value;
        }
        
        
        $check = $db->prepare("SELECT id FROM settings WHERE `key_name` = ?");
        $check->execute([$key]);
        $existing = $check->fetch();
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE settings SET value = ?, type = ?, description = ?, category = ?, updated_at = NOW() WHERE `key_name` = ?");
            $stmt->execute([$value, $type, $description, $category, $key]);
        } else {
            $stmt = $db->prepare("INSERT INTO settings (`key_name`, value, type, description, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$key, $value, $type, $description, $category]);
        }
        
        return true;
    }

    
    public static function getByCategory($category = null)
    {
        $db = Database::getConnection();
        
        if ($category) {
            $stmt = $db->prepare("SELECT * FROM settings WHERE category = ? ORDER BY `key_name`");
            $stmt->execute([$category]);
        } else {
            $stmt = $db->query("SELECT * FROM settings ORDER BY category, `key_name`");
        }
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new self($row);
        }
        return $results;
    }

    
    public static function getCategories()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT DISTINCT category FROM settings ORDER BY category");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}

