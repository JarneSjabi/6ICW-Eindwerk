<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\RouteController;

class RouteAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private RouteController $controller;

    public function __construct()
    {
        $this->controller = new RouteController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_routes',
                'index' => 'view_routes',
            ],
            'POST' => [
                'store' => 'manage_routes',
                'update' => 'manage_routes',
                'delete' => 'manage_routes',
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
