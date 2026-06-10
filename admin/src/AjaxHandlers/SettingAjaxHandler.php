<?php

namespace App\AjaxHandlers;

use App\Core\Request;
use App\Core\AjaxHandler;
use App\Controllers\SettingController;
use App\Models\Setting;
use App\Core\Utils;

class SettingAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];
    private SettingController $controller;

    public function __construct()
    {
        $this->controller = new SettingController();

        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'system_settings',
                'index' => 'system_settings'
            ],
            'POST' => [
                'store' => 'system_settings',
                'update' => 'system_settings',
                'bulk_update' => 'system_settings'
            ]
        ];

        $this->handlers = [
            'GET' => [
                'fetch' => function () {
                    $key = Request::get("key", null);
                    return $this->controller->show($key);
                },
                'index' => function () {
                    return $this->controller->index();
                },
                'get_digisync' => function () {
                    return $this->getDigisyncSettings();
                }
            ],
            'POST' => [
                'store' => function () {
                    return $this->controller->store();
                },
                'update' => function () {
                    $key = Request::get("key", null);
                    return $this->controller->update($key);
                },
                'bulk_update' => function () {
                    return $this->controller->bulkUpdate();
                },
            ]
        ];
    }

    
    private function getDigisyncSettings()
    {
        try {
            $settings = Setting::getByCategory('digisync');
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = [
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'description' => $setting->description
                ];
            }

            return $this->success('DIGI sync settings retrieved', $result);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen DIGI sync instellingen: ' . $e->getMessage());
        }
    }
}

