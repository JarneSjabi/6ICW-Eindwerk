<?php
// Hook core class
/* ⚠️ WARNING: If you want to use hooks: MAKE SURE TO SET THE METHODS OF THE CLASS USING HOOKS TO PROTECTED OR PRIVATE.
    PUBLIC METHODS WILL SILTENTLY FAIL TO EXECUTE HOOKS
*/

namespace App\Core;

class Hook
{
    public const BEFORE = 'before';
    public const AFTER = 'after';

    private static array $hooks = [];

    /**
     * Register a new hook for a method
     * @param string $className The full class name
     * @param string $methodName The method name to hook
     * @param callable $callback Function to be called
     * @param string $type 'before' or 'after'
     * @param int $priority Priority of the hook (lower number = earlier execution)
     * @return void
     */
    public static function register(string $className, string $methodName, callable $callback, string $type = self::BEFORE, int $priority = 10): void
    {
        $key = self::buildKey($className, $methodName, $type);

        if (!isset(self::$hooks[$key])) {
            self::$hooks[$key] = [];
        }

        self::$hooks[$key][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort hooks by priority
        usort(self::$hooks[$key], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Execute hooks for a method
     * @param string $className The full class name
     * @param string $methodName The method name
     * @param string $type 'before' or 'after'
     * @param array $args Arguments to pass to the hook callbacks
     * @return bool True if all hooks succeeded, false if any hook returned false
     */
    public static function execute(string $className, string $methodName, string $type, array $args = []): bool
    {
        $key = self::buildKey($className, $methodName, $type);

        if (!isset(self::$hooks[$key])) {
            return true; // No hooks registered, continue execution
        }

        foreach (self::$hooks[$key] as $hook) {
            $result = call_user_func_array($hook['callback'], $args);
            if ($result === false) {
                return false; // Stop if any hook returns false
            }
        }

        return true;
    }

    /**
     * Check if hooks exist for a method
     * @param string $className The full class name
     * @param string $methodName The method name
     * @param string $type 'before' or 'after'
     * @return bool
     */
    public static function exists(string $className, string $methodName, string $type): bool
    {
        $key = self::buildKey($className, $methodName, $type);
        return isset(self::$hooks[$key]) && !empty(self::$hooks[$key]);
    }

    /**
     * Remove all hooks for a method
     * @param string $className The full class name
     * @param string $methodName The method name
     * @param string $type Optional. If not provided, removes both before and after hooks
     * @return void
     */
    public static function remove(string $className, string $methodName, ?string $type = null): void
    {
        if ($type === null) {
            // Remove both before and after hooks
            $beforeKey = self::buildKey($className, $methodName, self::BEFORE);
            $afterKey = self::buildKey($className, $methodName, self::AFTER);
            unset(self::$hooks[$beforeKey], self::$hooks[$afterKey]);
        } else {
            $key = self::buildKey($className, $methodName, $type);
            unset(self::$hooks[$key]);
        }
    }

    /**
     * Build a unique key for storing hooks
     * @param string $className The full class name
     * @param string $methodName The method name
     * @param string $type 'before' or 'after'
     * @return string
     */
    private static function buildKey(string $className, string $methodName, string $type): string
    {
        return "{$className}::{$methodName}::{$type}";
    }
}
