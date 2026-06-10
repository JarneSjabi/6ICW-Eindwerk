<?php
// Hookable core class
/* ⚠️ WARNING: If you want to use hooks: MAKE SURE TO SET THE METHODS OF THE CLASS USING HOOKS TO PROTECTED OR PRIVATE.
    PUBLIC METHODS WILL SILTENTLY FAIL TO EXECUTE HOOKS
*/

namespace App\Core;

use App\Core\Hook;

trait Hookable
{
    /**
     * Register a hook for a method in this class
     * @param string $methodName Method name to hook into
     * @param callable $callback Function to be called
     * @param string $type 'before' or 'after'
     * @param int $priority Priority of the hook (lower number = earlier execution)
     * @return void
     */
    protected function registerHook(string $methodName, callable $callback, string $type = Hook::BEFORE, int $priority = 10): void
    {
        Hook::register(get_class($this), $methodName, $callback, $type, $priority);
    }

    public function __call($method, $args)
    {
        $className = get_class($this);

        // Check if method exists in current class or parent
        $methodExists = method_exists($this, $method);
        $parentClass = get_parent_class($this);
        $parentHasMethod = $parentClass && method_exists($parentClass, $method);

        if (!$methodExists && !$parentHasMethod) {
            throw new \BadMethodCallException("Method {$method} does not exist");
        }

        // Execute 'before' hooks
        $beforeResult = Hook::execute($className, $method, Hook::BEFORE, $args);
        if ($beforeResult === false) {
            return null; // Hook cancelled the execution
        }

        // Call the actual method using Reflection to bypass __call recursion
        $reflectionMethod = new \ReflectionMethod($parentHasMethod ? $parentClass : $className, $method);
        $reflectionMethod->setAccessible(true);
        $result = $reflectionMethod->invokeArgs($this, $args);

        // Execute 'after' hooks
        Hook::execute($className, $method, Hook::AFTER, array_merge($args, [$result]));

        return $result;
    }
}
