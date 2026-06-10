<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Route;

class RouteController extends Controller
{
    protected $table = 'routes';

    protected function statistics()
    {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->query("SELECT 
                COUNT(*) as total_routes,
                COUNT(CASE WHEN status = 'planned' THEN 1 END) as planned_routes,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_routes,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_routes,
                SUM(distance_km) as total_distance_km
                FROM routes");
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }
}
