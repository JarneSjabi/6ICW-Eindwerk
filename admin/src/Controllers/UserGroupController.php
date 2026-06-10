<?php

namespace App\Controllers;

use App\Core\Hook;
use App\Core\Utils;
use App\Core\Controller;
use App\Models\UserGroup;
use App\Models\UserGroupPermissions;


function groupPermissionsHelper($id)
{
    $body = Utils::getRequestData();

    

    
    UserGroupPermissions::resetGroup($id);

    
    foreach ($body as $index => $value) {
        if (strpos($index, 'perm_') === 0) {
            $pid = substr($index, strlen('perm_'));
            $cleanModel = new UserGroupPermissions();
            $cleanModel->group_id = $id;
            $cleanModel->permission_id = $pid;
            $cleanModel->value = true;
            $cleanModel->save();
        }
    }
}

class UserGroupController extends Controller
{
    protected $table = 'user_groups';

    protected function registerHooks()
    {
        

        
        $this->registerHook('update', function ($recordId) {
            groupPermissionsHelper($recordId);
        }, Hook::AFTER);

        
        $this->registerHook('store', function ($result) {
            $recordId = $result["data"]["id"];
            groupPermissionsHelper($recordId);
        }, Hook::AFTER);

        
        $this->registerHook('destroy', function ($recordId) {
            UserGroupPermissions::resetGroup($recordId);
        }, Hook::BEFORE);
    }

    
    protected function statistics()
    {
        try {
            $stats = UserGroup::getDashboardStats();
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }
}
