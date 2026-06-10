<?php

use App\Controllers\RideRequestController;

$rideController = new RideRequestController();
$rideIndex = $rideController->index();
$rideData = $rideIndex["data"];

$rides = $rideData['records'] ?? [];
$totalRides = $rideData['total'] ?? 0;
$currentPage = $rideData['page'] ?? 1;
$totalPages = $rideData['totalPages'] ?? 1;
$search = $rideData['search'] ?? '';
?>

<script src="/assets/js/main.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal('ride')">
                <i class="fa fa-plus"></i> Nieuwe Rit
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Zoek ritten..."
                value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
            <button onclick="performSearch()" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Rides Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Klant</th>
                    <th>Ophaallocatie</th>
                    <th>Bestemming</th>
                    <th>Ophaaltijd</th>
                    <th>Status</th>
                    <th>Voertuig</th>
                    <th>Prijs</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rides)): ?>
                    <tr>
                        <td colspan="9" class="text-center">Geen ritten gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rides as $ride): ?>
                        <tr>
                            <td><?= htmlspecialchars($ride['id']) ?></td>
                            <td>
                                <?php if ($ride['customer_name']): ?>
                                    <?= htmlspecialchars($ride['customer_name']) ?>
                                <?php elseif ($ride['user_id']): ?>
                                    Gebruiker #<?= htmlspecialchars($ride['user_id']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ride['pickup_address'] ?? 'Lat: ' . $ride['pickup_latitude'] . ', Lng: ' . $ride['pickup_longitude']) ?></td>
                            <td><?= htmlspecialchars($ride['dropoff_address'] ?? 'Lat: ' . $ride['dropoff_latitude'] . ', Lng: ' . $ride['dropoff_longitude']) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ride['requested_pickup_time']))) ?></td>
                            <td>
                                <span class="badge bg-<?= $ride['status'] === 'completed' ? 'success' : ($ride['status'] === 'in_progress' ? 'warning' : ($ride['status'] === 'pending' ? 'info' : ($ride['status'] === 'cancelled' ? 'danger' : 'secondary'))) ?>">
                                    <?= htmlspecialchars($ride['status']) ?>
                                </span>
                            </td>
                            <td><?= $ride['vehicle_id'] ? 'Voertuig #' . htmlspecialchars($ride['vehicle_id']) : '-' ?></td>
                            <td>
                                <?php if ($ride['actual_price_cents']): ?>
                                    €<?= number_format($ride['actual_price_cents'] / 100, 2) ?>
                                <?php elseif ($ride['estimated_price_cents']): ?>
                                    €<?= number_format($ride['estimated_price_cents'] / 100, 2) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewRide(<?= $ride['id'] ?>)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelRide(<?= $ride['id'] ?>)">
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
                            <a class="page-link" href="?tab=rides&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
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
function viewRide(id) {
    alert('View ride ' + id);
}

function cancelRide(id) {
    if (confirm('Weet je zeker dat je deze rit wilt annuleren?')) {
        alert('Cancel ride ' + id);
    }
}
</script>
