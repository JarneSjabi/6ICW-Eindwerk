<?php

namespace App\AjaxHandlers;

use App\Core\AjaxHandler;
use App\Core\Request;
use App\Core\Audit;

class AuditAjaxHandler extends AjaxHandler
{
    protected array $handlers;
    protected array $handlerPermissions = [];

    public function __construct()
    {
        $this->handlerPermissions = [
            'GET' => [
                'fetch' => 'view_assortment'
            ],
            'POST' => [
                'revert' => 'manage_auditlog'
            ]
        ];

        $this->handlers = [
            'GET' => [
                'fetch' => function () {
                    $id = Request::get('id');
                    if (!$id) return ['success' => false, 'message' => 'Geen audit id gegeven'];

                    try {
                        $db = \App\Core\Database::getConnection();
                        $stmt = $db->prepare("
                            SELECT al.*, u.firstname, u.lastname, u.email 
                            FROM audit_log al 
                            LEFT JOIN users u ON u.id = al.user_id 
                            WHERE al.id = ?
                        ");
                        $stmt->execute([$id]);
                        $log = $stmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if (!$log) {
                            return ['success' => false, 'message' => 'Log niet gevonden'];
                        }
                        
                        return ['success' => true, 'data' => $log];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'message' => 'Fout bij ophalen: ' . $e->getMessage()];
                    }
                }
            ],
            'POST' => [
                'revert' => function () {
                    $id = Request::get('id');
                    if (!$id) return ['success' => false, 'message' => 'Geen audit id gegeven'];

                    try {
                        $res = Audit::revert((int)$id);
                        return $res;
                    } catch (\Throwable $e) {
                        return ['success' => false, 'message' => 'Fout bij ongedaan maken: ' . $e->getMessage()];
                    }
                }
            ]
        ];
    }
}
