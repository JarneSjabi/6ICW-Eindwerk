<?php
// Session core class
namespace App\Core;

class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public static function clear()
    {
        session_destroy();
    }

    public static function flash($key, $message = null)
    {
        if ($message === null) {
            $message = Session::get($key);
            Session::remove($key);
            return $message;
        }

        Session::set($key, $message);
    }

    public static function regenerate()
    {
        session_regenerate_id(true);
    }
}
