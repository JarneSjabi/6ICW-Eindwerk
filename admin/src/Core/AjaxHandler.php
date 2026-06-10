<?php

namespace App\Core;

use App\Core\Authentication;


abstract class AjaxHandler
{
    protected array $handlerPermissions = [];
    
    protected array $handlers = [];

    public function handleRequest(string $method, string $action): void
    {
        if (isset($this->handlers[$method][$action])) {
            
            if (isset($this->handlerPermissions[$method][$action])) {
                $Authentication = new Authentication();
                if (!$Authentication->hasPermission($this->handlerPermissions[$method][$action])) {
                    $this->sendErrorResponse("Niet gemachtigd: Ontbrekende permissies voor de gevraagde actie $action met methode $method", 401);
                }
            }

            $handler = $this->handlers[$method][$action];

            try {
                $result = call_user_func($handler);
                $this->sendJsonResponse($result);
            } catch (\Exception $e) {
                $this->sendErrorResponse($e->getMessage());
            }
        } else {
            $this->sendErrorResponse("De gevraagde actie '$action' wordt niet ondersteund voor methode $method");
        }
    }

    protected function sendJsonResponse($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function sendErrorResponse(string $message, int $code = 400): void
    {
        http_response_code($code);
        $this->sendJsonResponse(['error' => $message]);
        exit();
    }
}
