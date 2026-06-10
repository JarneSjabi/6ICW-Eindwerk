<?php

use App\Core\Database;

$db = Database::getConnection();
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;
$filterResolved = $_GET['filter_resolved'] ?? '';

$sql = "SELECT ri.*, v.license_plate, rr.customer_name 
        FROM ride_interrupts ri 
        LEFT JOIN vehicles v ON ri.vehicle_id = v.id 
        LEFT JOIN ride_requests rr ON ri.ride_request_id = rr.id 
        WHERE 1=1";

$params = [];
if ($filterResolved !== '') {
    $sql .= " AND ri.resolved = ?";
    $params[] = $filterResolved;
}

$sql .= " ORDER BY ri.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$interrupts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$countSql = "SELECT COUNT(*) as total FROM ride_interrupts WHERE 1=1";
$countParams = [];
if ($filterResolved !== '') {
    $countSql .= " AND resolved = ?";
    $countParams[] = $filterResolved;
}
$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalInterrupts = $countStmt->fetch()['total'];
$totalPages = ceil($totalInterrupts / $limit);
?>

<div class="tab-content">
    <div class="tab-header">
        <h2><i class="fa fa-pause-circle"></i> Ritonderbrekingen</h2>
        <div class="tab-actions">
            <select class="form-select" onchange="window.location.href='?tab=ride_interrupts&filter_resolved=' + this.value">
                <option value="" <?= $filterResolved === '' ? 'selected' : '' ?>>Alle</option>
                <option value="0" <?= $filterResolved === '0' ? 'selected' : '' ?>>Niet opgelost</option>
                <option value="1" <?= $filterResolved === '1' ? 'selected' : '' ?>>Opgelost</option>
            </select>
        </div>
    </div>

    <!-- Interrupts Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Rit</th>
                    <th>Voertuig</th>
                    <th>Type</th>
                    <th>Beschrijving</th>
                    <th>Locatie</th>
                    <th>Duur (min)</th>
                    <th>Status</th>
                    <th>Gemaakt op</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interrupts)): ?>
                    <tr>
                        <td colspan="10" class="text-center">Geen onderbrekingen gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($interrupts as $interrupt): ?>
                        <tr>
                            <td><?= htmlspecialchars($interrupt['id']) ?></td>
                            <td>
                                <?php if ($interrupt['customer_name']): ?>
                                    <?= htmlspecialchars($interrupt['customer_name']) ?>
                                <?php else: ?>
                                    Rit #<?= htmlspecialchars($interrupt['ride_request_id']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($interrupt['license_plate'] ?? 'Voertuig #' . $interrupt['vehicle_id']) ?></td>
                            <td>
                                <span class="badge bg-warning">
                                    <?= htmlspecialchars($interrupt['interrupt_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($interrupt['description'] ?? '-') ?></td>
                            <td>
                                <?php if ($interrupt['latitude'] && $interrupt['longitude']): ?>
                                    <?= number_format($interrupt['latitude'], 4) ?>, <?= number_format($interrupt['longitude'], 4) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($interrupt['duration_minutes'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $interrupt['resolved'] ? 'success' : 'danger' ?>">
                                    <?= $interrupt['resolved'] ? 'Opgelost' : 'Niet opgelost' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($interrupt['created_at']))) ?></td>
                            <td>
                                <?php if (!$interrupt['resolved']): ?>
                                    <button class="btn btn-sm btn-success" onclick="resolveInterrupt(<?= $interrupt['id'] ?>)">
                                        <i class="fa fa-check"></i> Oplossen
                                    </button>
                                <?php endif; ?>
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
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=ride_interrupts&page=<?= $i ?><?= $filterResolved !== '' ? '&filter_resolved=' . urlencode($filterResolved) : '' ?>">
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
function resolveInterrupt(id) {
    if (confirm('Weet je zeker dat je deze onderbreking als opgelost wilt markeren?')) {
        
        alert('Resolve interrupt ' + id);
    }
}
</script>
