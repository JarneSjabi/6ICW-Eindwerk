<?php

namespace App\Core;

use App\Core\Config;
use App\Core\Request;
use App\Core\AjaxHandler;

class Application
{
    
    public static function callInitialization(): void
    {
        
        Application::checkCORS();
        Application::initializeAjaxHandlers();
        Application::setErrorReporting();
    }

    
    public static function initializeAjaxHandlers(): void
    {
        
        if (!empty($_GET['ajax']) && !empty($_GET['action'])) {
            $handlerClass = Request::get('ajax', '');
            $action = Request::get('action', '');
            $requestMethod = Request::getRequestMethod();

            
            $className = self::convertToClassName($handlerClass);
            $fullClassName = "App\\AjaxHandlers\\{$className}";

            
            if (class_exists($fullClassName)) {
                $handlerInstance = new $fullClassName();

                if ($handlerInstance instanceof AjaxHandler) {
                    
                    $handlerInstance->handleRequest($requestMethod, $action);
                    exit; 
                }
            }

            
            http_response_code(404);
            echo json_encode(['error' => 'AJAX-handler niet gevonden of ongeldig']);
            exit;
        }
    }

    
    public static function checkCORS(): void
    {
        if(Config::get('DEBUG_MODE') === true) {
            return; 
        }
        $allowedOrigins = [Config::get('BASE_URL'), 'http://localhost', 'http://localhost:8000', 'http://localhost:8080'];
        if (!Utils::isAllowedOrigin($allowedOrigins)) {
            http_response_code(403);
            echo "Forbidden: Invalid origin.";
            exit;
        }
    }

    
    public static function setErrorReporting()
    {
        $override = Config::get('ERROR_REPORTING_OVERRIDE');
        
        if($override != null)
        {
            error_reporting($override);
            return;
        }

        if (Config::get('DEBUG_MODE') === true) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
            ini_set('display_errors', '0');
        }
    }

    
    private static function convertToClassName(string $name): string
    {
        
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return strip_tags($name) . 'AjaxHandler';
    }

    public static function generateQuote(): string
    {
        $quotes = Config::get('QUOTES') ?? [];

        return $quotes[array_rand($quotes)] ?? "";
    }
}
