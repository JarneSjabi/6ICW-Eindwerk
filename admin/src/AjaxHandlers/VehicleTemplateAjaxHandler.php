<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\VehicleTemplateController;

class VehicleTemplateAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private VehicleTemplateController $controller;

    public function __construct()
    {
        $this->controller = new VehicleTemplateController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_vehicle_templates',
                'index' => 'view_vehicle_templates',
            ],
            'POST' => [
                'store' => 'manage_vehicle_templates',
                'update' => 'manage_vehicle_templates',
                'delete' => 'manage_vehicle_templates',
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
