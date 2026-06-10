<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\VehicleTemplate;

class VehicleTemplateController extends Controller
{
    protected $table = 'vehicle_templates';

    protected function statistics()
    {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->query("SELECT 
                COUNT(*) as total_templates,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_templates
                FROM vehicle_templates");
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }
}
