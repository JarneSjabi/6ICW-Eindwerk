<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\RideRequest;

class RideRequestController extends Controller
{
    protected $table = 'ride_requests';

    protected function statistics()
    {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->query("SELECT 
                COUNT(*) as total_rides,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_rides,
                COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_rides,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_rides,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rides,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_rides,
                AVG(actual_price_cents) as avg_price_cents,
                SUM(actual_distance_km) as total_distance_km
                FROM ride_requests");
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }
}
