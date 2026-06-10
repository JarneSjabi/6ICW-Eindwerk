<?php
// Database core class
namespace App\Core;

use PDO;
use Exception;
use PDOException;
use App\Core\Config;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = Config::get('DB_HOST');
                $db = Config::get('DB_DATABASE');
                $user = Config::get('DB_USERNAME');
                $pass = Config::get('DB_PASSWORD');
                $charset = 'utf8mb4';

                $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::$connection = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new Exception('Verbinding met database mislukt: ' . $e->getMessage());
            }
        }
        return self::$connection;
    }
}
