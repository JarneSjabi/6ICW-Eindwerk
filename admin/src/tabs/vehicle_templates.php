<?php

use App\Controllers\VehicleTemplateController;

$templateController = new VehicleTemplateController();
$templateIndex = $templateController->index();
$templateData = $templateIndex["data"];

$templates = $templateData['records'] ?? [];
$totalTemplates = $templateData['total'] ?? 0;
$currentPage = $templateData['page'] ?? 1;
$totalPages = $templateData['totalPages'] ?? 1;
$search = $templateData['search'] ?? '';
?>

<script src="/assets/js/main.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal('template')">
                <i class="fa fa-plus"></i> Nieuw Template
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Zoek templates..."
                value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
            <button onclick="performSearch()" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- Templates Table -->
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Merk</th>
                    <th>Model</th>
                    <th>Capaciteit</th>
                    <th>Bereik (km)</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Geen templates gevonden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <tr class="template-row" onclick="editTemplate(<?= $template['id'] ?>, event)" style="cursor: pointer;">
                            <td><?= htmlspecialchars($template['id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($template['name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($template['brand'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($template['model'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($template['capacity']) ?> pers.</span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?= htmlspecialchars($template['max_range_km'] ?? '-') ?> km</span>
                            </td>
                            <td onclick="event.stopPropagation();">
                                <button class="btn btn-sm btn-primary" onclick="editTemplate(<?= $template['id'] ?>)">
                                    <i class="fa fa-edit"></i> Bekijk
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
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
                            <a class="page-link" href="?tab=vehicle_templates&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
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
function editTemplate(id, event) {
    
    if (event && event.target.closest('.btn')) {
        return; 
    }
    
    alert('View template ' + id);
    
}

function deleteTemplate(id) {
    event.stopPropagation();
    if (confirm('Weet je zeker dat je dit template wilt verwijderen?')) {
        alert('Delete template ' + id);
        
    }
}
</script>

<style>

.template-row {
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.template-row:hover {
    background-color: #e3f2fd;
    box-shadow: inset 0 0 0 1px rgba(33, 150, 243, 0.3);
}


.table .badge {
    padding: 0.4rem 0.6rem;
    font-weight: 500;
}

.badge.bg-primary {
    background-color: #1976d2 !important;
}

.badge.bg-success {
    background-color: #388e3c !important;
}

.badge.bg-info {
    background-color: #0288d1 !important;
}


.template-row .btn {
    transition: all 0.2s ease;
}

.template-row .btn:hover {
    transform: translateY(-2px);
}

.template-row strong {
    color: #1565c0;
    display: block;
    margin-bottom: 2px;
}
</style>
