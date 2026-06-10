<?php
// Abstract class for Models
namespace App\Core;

use PDO;
use App\Core\Database;

abstract class Model
{
    protected static $table;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected $dirty = [];
    protected $casts = [];
    protected $db;
    protected $fillable = [];
    protected $hidden = [];
    protected $whereConditions = [];
    protected $orderBy = '';

    public function __construct(array $attributes = [])
    {
        $this->db = Database::getConnection();
        $this->fill($attributes);
    }

    public static function query()
    {
        return new static();
    }

    public static function where(string $column, $operator, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $instance = new static();
        $instance->whereConditions[] = compact('column', 'operator', 'value');

        return $instance;
    }

    public function get()
    {
        $sql = "SELECT * FROM " . static::$table;
        $params = [];

        if (!empty($this->whereConditions)) {
            $whereClauses = [];
            foreach ($this->whereConditions as $condition) {
                $whereClauses[] = "{$condition['column']} {$condition['operator']} ?";
                $params[] = $condition['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $model = new static();
            $model->exists = true;
            $model->fill($data);
            $results[] = $model;
        }

        return $results;
    }

    /**
     * Add ORDER BY clause to query builder
     */
    public function orderBy(string $column, string $direction = 'ASC')
    {
        $this->orderBy = sprintf('%s %s', $column, strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public static function firstWhere(string $column, $value)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$value]);

        if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $model = new static($data);
            $model->exists = true;
            return $model;
        }

        return null;
    }

    /**
     * Get statistics for dashboard
     */
    public static function getDashboardStats()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) AS total_count FROM " . static::$table);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    public static function getPaginated($search = '', $page = 1, $limit = 20, $filters = [])
    {
        $db = Database::getConnection();
        $offset = ($page - 1) * $limit;
        $table = static::$table;

        $query = "SELECT * FROM {$table}";
        $params = [];

        // Track whether WHERE has been added
        $whereAdded = false;

        // Add search conditions
        if (!empty($search)) {
            $searchColumns = static::getSearchableColumns();
            if (!empty($searchColumns)) {
                $searchConditions = [];
                foreach ($searchColumns as $column) {
                    $searchConditions[] = "{$column} LIKE ?";
                    $params[] = "%{$search}%";
                }

                $query .= $whereAdded ? " AND " : " WHERE ";
                $query .= "(" . implode(' OR ', $searchConditions) . ")";
                $whereAdded = true;
            }
        }

        // Add filters
        foreach ($filters as $column => $value) {
            if (!empty($value)) {
                $query .= $whereAdded ? " AND " : " WHERE ";
                $query .= "{$column} = ?";
                $params[] = $value;
                $whereAdded = true;
            }
        }

        // Count query
        $countQuery = "SELECT COUNT(*) as total FROM (" . str_replace("SELECT *", "SELECT 1", $query) . ") as count_table";
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['total'];

        // Pagination
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        // Execute query
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert results to arrays and hide sensitive fields
        $results = [];
        foreach ($data as $row) {
            $model = new static($row);
            $model->exists = true;
            $results[] = $model->toArray();  // Use toArray() to remove hidden fields
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

    /**
     * Get searchable columns for this model
     */
    public static function getSearchableColumns(): array
    {
        // Default implementation - override in child models
        return ['name', 'description'];
    }

    /**
     * Check if record can be deleted
     */
    public function canDelete(): bool
    {
        // Default implementation - override in child models
        return true;
    }

    /**
     * Get error message if record cannot be deleted
     */
    public function getDeleteErrorMessage(): string
    {
        return 'Dit record kan niet worden verwijderd vanwege afhankelijkheden';
    }

    public function __get(string $key)
    {
        if (method_exists($this, $key)) {
            return $this->$key();
        }

        if (isset($this->attributes[$key])) {
            if (isset($this->casts[$key])) {
                return $this->cast($this->attributes[$key], $this->casts[$key]);
            }
            return $this->attributes[$key];
        }

        return null;
    }

    public function __set(string $key, $value)
    {
        if (in_array($key, $this->fillable) || empty($this->fillable)) {
            if (!array_key_exists($key, $this->attributes) || $this->attributes[$key] !== $value) {
                if (!in_array($key, $this->dirty, true)) {
                    $this->dirty[] = $key;
                }
            }
            $this->attributes[$key] = $value;
        }
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            // Always allow primary key and fillable attributes
            if ($key === $this->primaryKey || in_array($key, $this->fillable) || empty($this->fillable)) {
                $currentValue = $this->attributes[$key] ?? null;

                if ($currentValue !== $value) {
                    $this->attributes[$key] = $value;

                    if (
                        $this->exists &&
                        (isset($this->original[$key]) && $this->original[$key] !== $value)
                    ) {
                        $this->dirty[] = $key;
                    }
                }
            }
        }

        if (!$this->exists) {
            $this->original = $this->attributes;
            $this->dirty = [];
        }
    }

    protected function getDirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->dirty as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $dirty[$key] = $this->attributes[$key];
            }
        }
        return $dirty;
    }

    public function isDirty(): bool
    {
        return !empty($this->dirty);
    }

    protected function cast($value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return is_array($value) ? $value : json_decode($value, true);
            case 'json':
                return json_decode($value, true);
            case 'datetime':
                return $value instanceof \DateTime ? $value : new \DateTime($value);
            default:
                return $value;
        }
    }

    public static function find($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE id = ?");
        $stmt->execute([$id]);

        if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $model = new static();
            $model->fill($data);     // eerst vullen
            $model->exists = true;   // dan markeren als bestaand
            $model->original = $model->attributes; // sync original
            return $model;
        }

        return null;
    }

    public static function all()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM " . static::$table);
        $results = [];

        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $model = new static($data);
            $model->exists = true;
            $results[] = $model;
        }

        return $results;
    }

    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        }

        return $this->insert();
    }

    protected function insert(): bool
    {
        // Exclude primary key from insert fields
        $fields = array_keys($this->attributes);
        $fields = array_filter($fields, function($field) {
            return $field !== $this->primaryKey;
        });
        
        $placeholders = array_fill(0, count($fields), '?');
        $values = [];
        
        foreach ($fields as $field) {
            $value = $this->attributes[$field];
            // Cast the value if there's a cast defined
            if (isset($this->casts[$field])) {
                $values[] = $this->cast($value, $this->casts[$field]);
            } else {
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO " . static::$table . " (" . implode(',', $fields) . ") 
                VALUES (" . implode(',', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($values)) {
            $this->{$this->primaryKey} = $this->db->lastInsertId();
            $this->exists = true;
            $this->original = $this->attributes;
            $this->dirty = [];
            return true;
        }

        return false;
    }

    protected function update(): bool
    {
        $dirtyAttributes = $this->getDirtyAttributes();
        // file_put_contents('test.json', json_encode($dirtyAttributes));
        if (empty($dirtyAttributes)) {
            return true;
        }

        $fields = [];
        $values = [];
        foreach ($dirtyAttributes as $field => $value) {
            if ($field !== $this->primaryKey) {
                $fields[] = "{$field} = ?";
                // Cast the value if there's a cast defined
                if (isset($this->casts[$field])) {
                    $values[] = $this->cast($value, $this->casts[$field]);
                } else {
                    $values[] = $value;
                }
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE " . static::$table . " SET " . implode(',', $fields) . " 
                WHERE {$this->primaryKey} = ?";
        $values[] = $this->attributes[$this->primaryKey];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        if ($result) {
            $this->original = $this->attributes;
            $this->dirty = [];
        }

        return $result;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $sql = "DELETE FROM " . static::$table . " WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$this->{$this->primaryKey}]);

        if ($result) {
            $this->exists = false;
        }

        return $result;
    }

    protected function hasColumn($column): bool
    {
        static $columns = [];

        if (!isset($columns[static::$table])) {
            $stmt = $this->db->prepare("DESCRIBE " . static::$table);
            $stmt->execute();
            $columns[static::$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return in_array($column, $columns[static::$table]);
    }

    public static function count()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as count FROM " . static::$table);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    public function toArray()
    {
        $array = $this->attributes;

        // Hide sensitive fields
        foreach ($this->hidden as $hidden) {
            unset($array[$hidden]);
        }

        return $array;
    }

    // Relationship methods
    public function hasMany($model, $foreignKey = null, $localKey = null)
    {
        if (!$foreignKey) {
            $foreignKey = strtolower(self::class_basename($this)) . '_id';
        }
        if (!$localKey) {
            $localKey = $this->primaryKey;
        }

        $modelClass = "App\\Models\\{$model}";
        return $modelClass::where($foreignKey, $this->{$localKey})->get();
    }

    public function belongsTo($model, $foreignKey = null, $ownerKey = null)
    {
        if (!$foreignKey) {
            $foreignKey = strtolower($model) . '_id';
        }
        if (!$ownerKey) {
            $ownerKey = 'id';
        }

        $modelClass = "App\\Models\\{$model}";
        return $modelClass::find($this->{$foreignKey});
    }

    // Helper function to get class basename
    protected static function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}
