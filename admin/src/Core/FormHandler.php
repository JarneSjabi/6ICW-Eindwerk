<?php
// This class is currently not used anywhere
namespace App\Core;

trait FormHandler
{
    protected array $errors = [];
    protected array $rules = [];
    protected array $messages = [];

    /**
     * Validate form data against rules
     */
    protected function validateForm(array $data, array $rules = null): bool
    {
        $this->errors = [];
        $validationRules = $rules ?? $this->rules;

        foreach ($validationRules as $field => $rules) {
            if (!isset($data[$field]) && in_array('required', explode('|', $rules))) {
                $this->errors[$field] = $this->messages[$field]['required'] ?? "Het veld $field is verplicht.";
                continue;
            }

            $value = $data[$field] ?? null;
            $ruleList = explode('|', $rules);

            foreach ($ruleList as $rule) {
                if (strpos($rule, ':') !== false) {
                    list($ruleName, $ruleValue) = explode(':', $rule);
                } else {
                    $ruleName = $rule;
                    $ruleValue = null;
                }

                $validationMethod = 'validate' . ucfirst($ruleName);
                if (method_exists($this, $validationMethod)) {
                    if (!$this->$validationMethod($value, $ruleValue)) {
                        $this->errors[$field] = $this->messages[$field][$ruleName] ?? "Het veld $field is ongeldig.";
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if field has error
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get error for field
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Basic validation rules
     */
    protected function validateRequired($value): bool
    {
        return !empty($value);
    }

    protected function validateEmail($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin($value, $min): bool
    {
        if (is_numeric($value)) {
            return $value >= $min;
        }
        return strlen($value) >= $min;
    }

    protected function validateMax($value, $max): bool
    {
        if (is_numeric($value)) {
            return $value <= $max;
        }
        return strlen($value) <= $max;
    }

    protected function validateNumeric($value): bool
    {
        return is_numeric($value);
    }

    protected function validateDate($value): bool
    {
        return strtotime($value) !== false;
    }

    protected function validateBoolean($value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true);
    }

    protected function validateRegex($value, $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    protected function validateUnique($value, $params): bool
    {
        list($table, $column, $except) = array_pad(explode(',', $params), 3, null);
        $query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $bindings = [$value];

        if ($except) {
            $query .= " AND id != ?";
            $bindings[] = $except;
        }

        $stmt = Database::getConnection()->prepare($query);
        $stmt->execute($bindings);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result['count'] == 0;
    }
}
