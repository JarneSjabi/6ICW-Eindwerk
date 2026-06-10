<?php

namespace App\Core;

use PDO;
use App\Core\Authentication;
use App\Core\Request;
use App\Core\Database;

class Audit
{
    
    public static function log($action, $entity_type = null, $entity_id = null, $old_value = null, $new_value = null): bool
    {
        $db = Database::getConnection();

        $sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $db->prepare($sql);

        
        $old_value = is_array($old_value) || is_object($old_value) ? json_encode($old_value, JSON_UNESCAPED_UNICODE) : $old_value;
        $new_value = is_array($new_value) || is_object($new_value) ? json_encode($new_value, JSON_UNESCAPED_UNICODE) : $new_value;

        
        $Authentication = new Authentication();
        $user_id = $Authentication->id() ?? null;

        return $stmt->execute([$user_id, $action, $entity_type, $entity_id, $old_value, $new_value, Request::getIPAddress(), Request::getUserAgent()]);
    }

    
    public static function getLogsWhere(string $field, mixed $value)
    {
        $db = Database::getConnection();

        $sql = "SELECT * FROM audit_log WHERE {$field} = ?";

        $stmt = $db->prepare($sql);

        $stmt->execute([$value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public static function getLogsForEntity(string $entityType, mixed $entityId)
    {
        $db = Database::getConnection();

        
        $sql = "SELECT al.*, u.firstname, u.lastname, u.email
                FROM audit_log al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE al.entity_type = ? AND al.entity_id = ?
                ORDER BY al.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$entityType, $entityId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        foreach ($rows as &$r) {
            if (!empty($r['firstname']) || !empty($r['lastname'])) {
                $r['display_name'] = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
            } elseif (!empty($r['email'])) {
                $r['display_name'] = $r['email'];
            } elseif (!empty($r['user_id'])) {
                $r['display_name'] = 'User #' . $r['user_id'];
            } else {
                $r['display_name'] = 'System';
            }

            
            $r['action_label'] = self::humanizeAction($r['action'] ?? '');
            $r['action_icon'] = self::getActionIcon($r['action'] ?? '');
        }

        return $rows;
    }

    
    public static function revert(int $auditId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM audit_log WHERE id = ? LIMIT 1');
        $stmt->execute([$auditId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'Audit entry not found'];
        }

        if (empty($row['entity_type']) || empty($row['entity_id'])) {
            return ['success' => false, 'message' => 'Geen entiteit gekoppeld aan deze audit'];
        }

        $old = $row['old_value'] ? json_decode($row['old_value'], true) : null;

        if ($old === null) {
            return ['success' => false, 'message' => 'Geen oude waarde beschikbaar om te herstellen'];
        }

        
        try {
            $Authentication = new Authentication();
            if (!$Authentication->hasPermission('manage_audit')) {
                return ['success' => false, 'message' => 'Unauthorized'];
            }
        } catch (\Throwable $e) {
            
        }

        
        $modelClass = $row['entity_type'];
        if (!class_exists($modelClass)) {
            
            if (strpos($modelClass, 'App\\Models') === false) {
                $try = "App\\Models\\" . ltrim($modelClass, '\\');
                if (class_exists($try)) $modelClass = $try;
            }
        }

        if (!class_exists($modelClass)) {
            return ['success' => false, 'message' => 'Modelklasse niet gevonden: ' . $row['entity_type']];
        }

        
        try {
            $db->beginTransaction();

            $model = $modelClass::find($row['entity_id']);
            if (!$model) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Entiteit niet gevonden'];
            }

            
            $existingKeys = array_keys($model->toArray());
            $allowed = array_intersect($existingKeys, array_keys($old));

            if (empty($allowed)) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Geen geldige velden om te herstellen'];
            }

            foreach ($allowed as $k) {
                
                if ($k === ($model->primaryKey ?? 'id')) continue;
                $model->{$k} = $old[$k];
            }

            $saved = $model->save();

            if ($saved) {
                
                self::log('revert', $row['entity_type'], $row['entity_id'], $row['new_value'], json_encode($old));
                $db->commit();
                return ['success' => true, 'message' => 'Ongedaan maken voltooid'];
            }

            $db->rollBack();
            return ['success' => false, 'message' => 'Kon entiteit niet opslaan'];
        } catch (\Throwable $e) {
            try {
                $db->rollBack();
            } catch (\Throwable $_) {
            }
            return ['success' => false, 'message' => 'Fout bij ongedaan maken: ' . $e->getMessage()];
        }
    }

    
    
    public static function humanizeAction(string $action): string
    {
        
        
        if (empty($action)) return '';

        
        if (strpos($action, '__') !== false) {
            $parts = explode('__', $action);
            $verb = end($parts);
        } else {
            $parts = preg_split('/[\.\/]/', $action);
            $verb = end($parts);
        }

        $verb = strtolower($verb);
        $map = [
            'store' => 'Aanmaken',
            'update' => 'Bijwerken',
            'delete' => 'Verwijderen',
            'revert' => 'Ongedaan maken',
        ];

        return $map[$verb] ?? ucfirst($verb);
    }

    
    public static function getActionIcon(string $action): string
    {
        
        
        if (empty($action)) return '';

        
        if (strpos($action, '__') !== false) {
            $parts = explode('__', $action);
            $verb = end($parts);
        } else {
            $parts = preg_split('/[\.\/]/', $action);
            $verb = end($parts);
        }

        $verb = strtolower($verb);
        $map = [
            'store' => 'fa fa-plus',
            'update' => 'fa fa-pencil',
            'delete' => 'fa fa-trash',
            'revert' => 'fa fa-undo',
        ];

        return $map[$verb] ?? ucfirst($verb);
    }
}
