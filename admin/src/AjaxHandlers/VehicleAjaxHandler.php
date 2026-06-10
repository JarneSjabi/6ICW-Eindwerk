<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\VehicleController;

class VehicleAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private VehicleController $controller;

    public function __construct()
    {
        $this->controller = new VehicleController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_vehicles',
                'index' => 'view_vehicles',
            ],
            'POST' => [
                'store' => 'manage_vehicles',
                'update' => 'manage_vehicles',
                'delete' => 'manage_vehicles',
            ]
        ];

        $this->handlers = [
            'GET' => [
                'fetch' => function () {
                    $id = Request::get("id", null);
                    return $this->controller->show($id);
                },
                'index' => function () {
                    return $this->controller->index();
                },
            ],
            'POST' => [
                'store' => function () {
                    return $this->controller->store();
                },
                'update' => function () {
                    $id = Request::get("id", null);
                    return $this->controller->update($id);
                },
                'delete' => function () {
                    $id = Request::get("id", null);
                    return $this->controller->destroy($id);
                },
            ]
        ];
    }
}
