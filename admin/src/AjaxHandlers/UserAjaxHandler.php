<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\UserController;

class UserAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private UserController $controller;

    public function __construct()
    {
        $this->controller = new UserController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_users',
                'index' => 'view_users'
            ],
            'POST' => [
                'store' => 'manage_users',
                'update' => 'manage_users',
                'delete' => 'manage_users',
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
