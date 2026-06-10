<?php

use App\Controllers\RouteController;

$routeController = new RouteController();
$routeIndex = $routeController->index();
$routeData = $routeIndex["data"];

$routes = $routeData['records'] ?? [];
$totalRoutes = $routeData['total'] ?? 0;
$currentPage = $routeData['page'] ?? 1;
$totalPages = $routeData['totalPages'] ?? 1;
$search = $routeData['search'] ?? '';
?>

<script src="/assets/js/main.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal('route')">
                <i class="fa fa-plus"></i> Nieuwe Route
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Zoek routes..."
                value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
            <button onclick="performSearch()" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Routes Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Naam</th>
                    <th>Voertuig</th>
                    <th>Start</th>
                    <th>Eind</th>
                    <th>Afstand (km)</th>
                    <th>Geschatte tijd</th>
                    <th>Werkelijke tijd</th>
                    <th>Dag</th>
                    <th>Uur</th>
                    <th>Status</th>
                    <th>Geplande start</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($routes)): ?>
                    <tr>
                        <td colspan="11" class="text-center">Geen routes gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($routes as $route): ?>
                        <tr>
                            <td><?= htmlspecialchars($route['id']) ?></td>
                            <td><?= htmlspecialchars($route['name'] ?? 'Route #' . $route['id']) ?></td>
                            <td>Voertuig #<?= htmlspecialchars($route['vehicle_id']) ?></td>
                            <td><?= number_format($route['start_latitude'], 4) ?>, <?= number_format($route['start_longitude'], 4) ?></td>
                            <td><?= number_format($route['end_latitude'], 4) ?>, <?= number_format($route['end_longitude'], 4) ?></td>
                            <td><?= htmlspecialchars($route['distance_km'] ?? '-') ?></td>
                            <td>
                                <span class="time-badge estimated" title="Geschatte rijduur">
                                    <?= htmlspecialchars($route['estimated_duration_minutes'] ?? '-') ?> min
                                </span>
                            </td>
                            <td>
                                <?php
                                    
                                    $actualDuration = '-';
                                    if (!empty($route['actual_start_time']) && !empty($route['actual_end_time'])) {
                                        $startTime = strtotime($route['actual_start_time']);
                                        $endTime = strtotime($route['actual_end_time']);
                                        $durationSeconds = $endTime - $startTime;
                                        $actualDuration = round($durationSeconds / 60); 
                                    }
                                ?>
                                <span class="time-badge actual" title="Werkelijke rijduur">
                                    <?= $actualDuration === '-' ? '-' : $actualDuration . ' min' ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                    
                                    $scheduledTime = strtotime($route['scheduled_start_time']);
                                    $dayOfWeek = date('N', $scheduledTime); 
                                    $dayNames = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
                                    $dayType = $dayNames[$dayOfWeek - 1];
                                    $isWeekend = in_array($dayOfWeek, [6, 7]);
                                ?>
                                <span class="badge <?= $isWeekend ? 'bg-danger' : 'bg-info' ?>">
                                    <?= htmlspecialchars($dayType) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                    
                                    $hour = date('H:00', strtotime($route['scheduled_start_time']));
                                    $hourInt = intval(date('H', strtotime($route['scheduled_start_time'])));
                                    
                                    
                                    $timePeriod = 'Nacht';
                                    if ($hourInt >= 6 && $hourInt < 12) $timePeriod = 'Ochtend';
                                    elseif ($hourInt >= 12 && $hourInt < 18) $timePeriod = 'Middag';
                                    elseif ($hourInt >= 18 && $hourInt < 24) $timePeriod = 'Avond';
                                ?>
                                <span class="badge bg-secondary" title="<?= $timePeriod ?>">
                                    <?= $hour ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $route['status'] === 'completed' ? 'success' : ($route['status'] === 'active' ? 'warning' : ($route['status'] === 'planned' ? 'info' : 'secondary')) ?>">
                                    <?= htmlspecialchars($route['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($route['scheduled_start_time']))) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewRoute(<?= $route['id'] ?>)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelRoute(<?= $route['id'] ?>)">
                                    <i class="fa fa-times"></i>
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
                            <a class="page-link" href="?tab=routes&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
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
function viewRoute(id) {
    alert('View route ' + id);
}

function cancelRoute(id) {
    if (confirm('Weet je zeker dat je deze route wilt annuleren?')) {
        alert('Cancel route ' + id);
    }
}
</script>

<style>

.time-badge {
    display: inline-block;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
}

.time-badge.estimated {
    background-color: #e3f2fd;
    color: #1565c0;
    border: 1px solid #90caf9;
}

.time-badge.actual {
    background-color: #f3e5f5;
    color: #6a1b9a;
    border: 1px solid #ce93d8;
}

.time-badge.actual:empty::after {
    content: '—';
    opacity: 0.6;
}


.table thead th {
    font-weight: 600;
    background-color: #f5f5f5;
    border-bottom: 2px solid #dee2e6;
}

.table tbody tr {
    transition: background-color 0.15s ease;
}

.table tbody tr:hover {
    background-color: #f9f9f9;
}


.badge {
    padding: 0.4rem 0.6rem;
    font-weight: 500;
    white-space: nowrap;
}
</style>
