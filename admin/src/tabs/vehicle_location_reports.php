<?php

use App\Core\Database;

$db = Database::getConnection();
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;
$filterVehicle = $_GET['filter_vehicle'] ?? '';


$sql = "SELECT vlr.*, v.license_plate, v.id as vehicle_id
        FROM vehicle_location_reports vlr 
        LEFT JOIN vehicles v ON vlr.vehicle_id = v.id 
        WHERE 1=1";
$params = [];

if ($filterVehicle !== '') {
    $sql .= " AND vlr.vehicle_id = ?";
    $params[] = $filterVehicle;
}

$sql .= " ORDER BY vlr.reported_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);


$countSql = "SELECT COUNT(*) as total FROM vehicle_location_reports vlr WHERE 1=1";
$countParams = [];
if ($filterVehicle !== '') {
    $countSql .= " AND vlr.vehicle_id = ?";
    $countParams[] = $filterVehicle;
}
$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalReports = $countStmt->fetch()['total'];
$totalPages = ceil($totalReports / $limit);


$vehicleStmt = $db->query("SELECT id, license_plate FROM vehicles WHERE is_active = 1 ORDER BY license_plate ASC");
$vehicles = $vehicleStmt->fetchAll(\PDO::FETCH_ASSOC);
?>

<div class="tab-content">
    <div class="tab-header">
        <h2><i class="fa fa-map-marker-alt"></i> Locatie Rapporten</h2>
        <div class="tab-actions">
            <select class="form-select" style="max-width: 250px;" onchange="window.location.href='?tab=vehicle_location_reports&filter_vehicle=' + this.value">
                <option value="" <?= $filterVehicle === '' ? 'selected' : '' ?>>Alle voertuigen</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= $vehicle['id'] ?>" <?= $filterVehicle === $vehicle['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vehicle['license_plate']) ?> (ID: <?= $vehicle['id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Voertuig</th>
                    <th>Locatie</th>
                    <th>Snelheid</th>
                    <th>Richting</th>
                    <th>Gerapporteerd op</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Geen locatierapporten gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['id']) ?></td>
                            <td><?= htmlspecialchars($report['license_plate'] ?? 'Voertuig #' . $report['vehicle_id']) ?></td>
                            <td><?= number_format($report['latitude'], 6) ?>, <?= number_format($report['longitude'], 6) ?></td>
                            <td><?= $report['speed_kmh'] ? number_format($report['speed_kmh'], 1) . ' km/h' : '-' ?></td>
                            <td><?= $report['heading'] ? number_format($report['heading'], 1) . '°' : '-' ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($report['reported_at']))) ?></td>
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
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=vehicle_location_reports&page=<?= $i ?><?= $filterVehicle ? '&filter_vehicle=' . urlencode($filterVehicle) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
