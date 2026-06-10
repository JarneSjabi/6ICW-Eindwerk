<?php

namespace App\AjaxHandlers;

use App\Controllers\AwaitingUserController;
use App\Core\Request;
use App\Core\AjaxHandler;

class AwaitingUserAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private AwaitingUserController $controller;

    public function __construct()
    {
        $this->controller = new AwaitingUserController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_users',
                'index' => 'view_users'
            ],
            'POST' => [
                'accept' => 'manage_users', 
                'deny' => 'manage_users', 
                'clear_all' => 'manage_users', 

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
                'accept' => function () {
                    return $this->controller->acceptAwatingUser((int)Request::get("id", 0));
                },
                'deny' => function () {
                    return $this->controller->denyAwatingUser((int)Request::get("id", 0));
                },
                'clear_all' => function () {
                    return $this->controller->clearAllAwatingUsers();
                },
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
