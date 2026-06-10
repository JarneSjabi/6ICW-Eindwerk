<?php

use App\Core\Database;
use App\Models\Vehicle;
use App\Models\VehicleTemplate;
use App\Models\RideRequest;
use App\Models\Route;




$db = Database::getConnection();


$vehicleStats = $db->query("SELECT 
    COUNT(*) as total_vehicles,
    COUNT(CASE WHEN status = 'available' THEN 1 END) as available_vehicles,
    COUNT(CASE WHEN status = 'in_use' THEN 1 END) as in_use_vehicles,
    COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_vehicles
    FROM vehicles WHERE is_active = 1")->fetch(\PDO::FETCH_ASSOC);

$rideStats = $db->query("SELECT 
    COUNT(*) as total_rides,
    COUNT(CASE WHEN status = 'pending' OR status = 'assigned' THEN 1 END) as pending_rides,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_rides,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rides,
    SUM(actual_distance_km) as total_distance_km,
    AVG(actual_price_cents) as avg_price_cents
    FROM ride_requests")->fetch(\PDO::FETCH_ASSOC);

$routeStats = $db->query("SELECT 
    COUNT(*) as total_routes,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_routes,
    COUNT(CASE WHEN status = 'planned' THEN 1 END) as planned_routes
    FROM routes")->fetch(\PDO::FETCH_ASSOC);

$templateStats = $db->query("SELECT COUNT(*) as total_templates FROM vehicle_templates WHERE is_active = 1")->fetch(\PDO::FETCH_ASSOC);
?>

<script src="/assets/js/main.js"></script>

<div class="dashboard-container">
    <!-- Header Section with Refresh -->
    <div class="dashboard-header">
        <div class="header-left">
            <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
            <p class="header-subtitle">Vlootoverzicht en ritstatistieken</p>
        </div>
        <div class="header-right">
            <button class="btn btn-outline-secondary me-2" onclick="location.reload()" title="Ververs dashboard">
                <i class="fas fa-sync-alt"></i> Vernieuwen
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Vehicle Statistics -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-car"></i> Voertuigen</h5>
                <h3><?= number_format($vehicleStats['total_vehicles'] ?? 0) ?></h3>
                <p class="text-muted">Totaal voertuigen</p>
                <div class="mt-2">
                    <span class="badge bg-success"><?= $vehicleStats['available_vehicles'] ?? 0 ?> Beschikbaar</span>
                    <span class="badge bg-warning"><?= $vehicleStats['in_use_vehicles'] ?? 0 ?> In gebruik</span>
                    <span class="badge bg-danger"><?= $vehicleStats['maintenance_vehicles'] ?? 0 ?> Onderhoud</span>
                </div>
            </div>
        </div>

        <!-- Ride Statistics -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-list"></i> Ritten</h5>
                <h3><?= number_format($rideStats['total_rides'] ?? 0) ?></h3>
                <p class="text-muted">Totaal ritten</p>
                <div class="mt-2">
                    <span class="badge bg-info"><?= $rideStats['pending_rides'] ?? 0 ?> In afwachting</span>
                    <span class="badge bg-warning"><?= $rideStats['in_progress_rides'] ?? 0 ?> Bezig</span>
                    <span class="badge bg-success"><?= $rideStats['completed_rides'] ?? 0 ?> Voltooid</span>
                </div>
                <?php if ($rideStats['total_distance_km']): ?>
                    <p class="mt-2"><small>Totaal afstand: <?= number_format($rideStats['total_distance_km'], 1) ?> km</small></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Route Statistics -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-route"></i> Routes</h5>
                <h3><?= number_format($routeStats['total_routes'] ?? 0) ?></h3>
                <p class="text-muted">Totaal routes</p>
                <div class="mt-2">
                    <span class="badge bg-info"><?= $routeStats['planned_routes'] ?? 0 ?> Gepland</span>
                    <span class="badge bg-warning"><?= $routeStats['active_routes'] ?? 0 ?> Actief</span>
                </div>
            </div>
        </div>

        <!-- Template Statistics -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-wrench"></i> Modellen</h5>
                <h3><?= number_format($templateStats['total_templates'] ?? 0) ?></h3>
                <p class="text-muted">Modellen</p>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-clock"></i> Recente Ritten</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recentRides = $db->query("SELECT id, customer_name, status, requested_pickup_time, created_at 
                                                FROM ride_requests 
                                                ORDER BY created_at DESC 
                                                LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($recentRides)): ?>
                        <p class="text-muted">Geen recente ritten</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($recentRides as $ride): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($ride['customer_name'] ?? 'Rit #' . $ride['id']) ?></strong><br>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ride['created_at'])) ?></small>
                                    </div>
                                    <span class="badge bg-<?= $ride['status'] === 'completed' ? 'success' : ($ride['status'] === 'in_progress' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars($ride['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-map-marker-alt"></i> Actieve Voertuigen</h5>
                </div>
                <div class="card-body">
                    <?php
                    $activeVehicles = $db->query("SELECT v.id, v.license_plate, v.status, v.battery_level, vlr.latitude AS current_latitude, vlr.longitude AS current_longitude 
                                                   FROM vehicles v 
                                                   LEFT JOIN (
                                                       SELECT vehicle_id, latitude, longitude
                                                       FROM vehicle_location_reports
                                                       WHERE id IN (
                                                           SELECT MAX(id) FROM vehicle_location_reports GROUP BY vehicle_id
                                                       )
                                                   ) vlr ON v.id = vlr.vehicle_id
                                                   WHERE v.status IN ('available', 'in_use') AND v.is_active = 1 
                                                   ORDER BY v.status DESC 
                                                   LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($activeVehicles)): ?>
                        <p class="text-muted">Geen actieve voertuigen</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($activeVehicles as $vehicle): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($vehicle['license_plate']) ?></strong><br>
                                        <small class="text-muted">Batterij: <?= htmlspecialchars($vehicle['battery_level']) ?>%</small>
                                    </div>
                                    <span class="badge bg-<?= $vehicle['status'] === 'available' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($vehicle['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


