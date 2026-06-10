<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\RideRequestController;

class RideRequestAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private RideRequestController $controller;

    public function __construct()
    {
        $this->controller = new RideRequestController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_rides',
                'index' => 'view_rides',
            ],
            'POST' => [
                'store' => 'manage_rides',
                'update' => 'manage_rides',
                'delete' => 'manage_rides',
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
