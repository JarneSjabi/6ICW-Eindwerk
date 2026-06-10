<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\UserGroupController;

class UserGroupAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private UserGroupController $controller;

    public function __construct()
    {
        $this->controller = new UserGroupController();

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
