<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\PermissionsController;

class PermissionsAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private PermissionsController $controller;

    public function __construct()
    {
        $this->controller = new PermissionsController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_users',
                'index' => 'view_users'
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
            ]
        ];
    }
}
