<?php
// Request core class
namespace App\Core;

class Request
{
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Get the current HTTP request method
     * Fallback: returns UNKNOWN
     */
    public static function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    }

    /**
     * Get the current HTTP remote address
     * Fallback: returns UNKNOWN
     */
    public static function getIPAddress(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    /**
     * Get the current HTTP user agent
     * Fallback: returns UNKNOWN
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    }

    /**
     * Detect AJAX or "Accept: application/json"
     */
    public static function isAjaxRequest(): bool
    {
        if (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        ) {
            return true;
        }
        return false;
    }
}
