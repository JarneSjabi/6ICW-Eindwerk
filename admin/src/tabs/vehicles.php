<?php

use App\Controllers\VehicleController;
use App\Controllers\VehicleTemplateController;
use App\Core\Database;

$vehicleController = new VehicleController();
$vehicleIndex = $vehicleController->index();
$vehicleData = $vehicleIndex["data"];

$vehicles = $vehicleData['records'] ?? [];
$totalVehicles = $vehicleData['total'] ?? 0;
$currentPage = $vehicleData['page'] ?? 1;
$totalPages = $vehicleData['totalPages'] ?? 1;
$search = $vehicleData['search'] ?? '';


$db = Database::getConnection();
$templateNames = [];
$templateRanges = [];
foreach ($vehicles as $vehicle) {
    if (!empty($vehicle['vehicle_template_id'])) {
        if (!isset($templateNames[$vehicle['vehicle_template_id']])) {
            $stmt = $db->prepare("SELECT name, max_range_km FROM vehicle_templates WHERE id = ?");
            $stmt->execute([$vehicle['vehicle_template_id']]);
            $template = $stmt->fetch(\PDO::FETCH_ASSOC);
            $templateNames[$vehicle['vehicle_template_id']] = $template['name'] ?? 'Unknown';
            $templateRanges[$vehicle['vehicle_template_id']] = $template['max_range_km'] ?? 0;
        }
    }
}
?>

<script src="/assets/js/main.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal('vehicle')">
                <i class="fa fa-plus"></i> Nieuw Voertuig
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Zoek voertuigen (kenteken, VIN)..."
                value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
            <button onclick="performSearch()" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Vehicles Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kenteken</th>
                    <th>VIN</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Batterij</th>
                    <th>Werkelijk bereik</th>
                    <th>Locatie</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Geen voertuigen gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><?= htmlspecialchars($vehicle['id']) ?></td>
                            <td><?= htmlspecialchars($vehicle['license_plate']) ?></td>
                            <td><?= htmlspecialchars($vehicle['vin'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($vehicle['vehicle_template_id'])): ?>
                                    <a href="?tab=vehicle_templates&action=view&id=<?= $vehicle['vehicle_template_id'] ?>" 
                                       class="badge bg-info text-decoration-none" 
                                       title="Klik om template te bekijken">
                                        <?= htmlspecialchars($templateNames[$vehicle['vehicle_template_id']] ?? 'Unknown') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $vehicle['status'] === 'available' ? 'success' : ($vehicle['status'] === 'in_use' ? 'warning' : ($vehicle['status'] === 'maintenance' ? 'danger' : 'secondary')) ?>">
                                    <?= htmlspecialchars($vehicle['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="battery-bar" title="Batterij: <?= $vehicle['battery_level'] ?? 'N/A' ?>%">
                                    <div class="battery-fill" style="width: <?= $vehicle['battery_level'] ?? 0 ?>%;">
                                        <small><?= htmlspecialchars($vehicle['battery_level'] ?? '-') ?>%</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $templateId = $vehicle['vehicle_template_id'];
                                    $maxRange = $templateRanges[$templateId] ?? 0;
                                    $batteryLevel = $vehicle['battery_level'] ?? 0;
                                    $realRange = ($maxRange * $batteryLevel) / 100;
                                ?>
                                <span class="badge bg-<?= $realRange > 100 ? 'success' : ($realRange > 50 ? 'warning' : 'danger') ?>" 
                                      title="Theoretisch bereik: <?= $maxRange ?> km @ 100%">
                                    <?= number_format($realRange, 1) ?> km
                                </span>
                            </td>
                            <td>
                                <?php if ($vehicle['current_latitude'] && $vehicle['current_longitude']): ?>
                                    <a href="?tab=mapview" title="Bekijk op kaart" class="text-decoration-none">
                                        <i class="fa fa-map-marker-alt"></i>
                                        <?= number_format($vehicle['current_latitude'], 4) ?>, <?= number_format($vehicle['current_longitude'], 4) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editVehicle(<?= $vehicle['id'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteVehicle(<?= $vehicle['id'] ?>)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=vehicles&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
function editVehicle(id) {
    
    alert('Edit vehicle ' + id);
}

function deleteVehicle(id) {
    if (confirm('Weet je zeker dat je dit voertuig wilt verwijderen?')) {
        
        alert('Delete vehicle ' + id);
    }
}

function openCreateModal(type) {
    
    alert('Create new ' + type);
}
</script>

<style>

.battery-bar {
    width: 100%;
    height: 24px;
    background-color: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #bdbdbd;
    position: relative;
    min-width: 100px;
}

.battery-fill {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 4px;
    color: white;
    font-weight: bold;
    background: linear-gradient(to right, #4caf50, #8bc34a);
    transition: width 0.3s ease, background-color 0.3s ease;
}

.battery-fill small {
    font-size: 11px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}


.battery-bar .battery-fill[style*="width: 0"],
.battery-bar .battery-fill[style*="width: 1"],
.battery-bar .battery-fill[style*="width: 2"] {
    background: linear-gradient(to right, #f44336, #e91e63);
}


.battery-bar .battery-fill[style*="width: 3"],
.battery-bar .battery-fill[style*="width: 4"],
.battery-bar .battery-fill[style*="width: 5"] {
    background: linear-gradient(to right, #ff9800, #ffc107);
}


.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}


.badge.bg-info {
    cursor: pointer;
    transition: all 0.2s ease;
}

.badge.bg-info:hover {
    opacity: 0.8;
    text-decoration: underline;
}
</style>
