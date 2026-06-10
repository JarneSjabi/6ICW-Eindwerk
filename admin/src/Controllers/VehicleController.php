<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Vehicle;

class VehicleController extends Controller
{
    protected $table = 'vehicles';

    protected function statistics()
    {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->query("SELECT 
                COUNT(*) as total_vehicles,
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available_vehicles,
                COUNT(CASE WHEN status = 'in_use' THEN 1 END) as in_use_vehicles,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_vehicles,
                COUNT(CASE WHEN status = 'out_of_service' THEN 1 END) as out_of_service_vehicles,
                AVG(battery_level) as avg_battery_level
                FROM vehicles WHERE is_active = 1");
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }
}
