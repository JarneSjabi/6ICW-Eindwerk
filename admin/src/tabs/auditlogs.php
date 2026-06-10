<?php

use App\Core\Utils;
use App\Core\Request;
use App\Core\Database;

$db = Database::getConnection();


$entityType = Request::get('entity_type', '');
$action = Request::get('action', '');
$userId = Request::get('user_id', '');
$dateFrom = Request::get('date_from', '');
$dateTo = Request::get('date_to', '');


$sql = "SELECT al.*, u.firstname, u.lastname, u.email 
        FROM audit_log al 
        LEFT JOIN users u ON u.id = al.user_id 
        WHERE 1=1";
$params = [];

if ($entityType) {
    $sql .= " AND al.entity_type = ?";
    $params[] = $entityType;
}

if ($action) {
    $sql .= " AND al.action LIKE ?";
    $params[] = "%{$action}%";
}

if ($userId) {
    $sql .= " AND al.user_id = ?";
    $params[] = $userId;
}

if ($dateFrom) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(\PDO::FETCH_OBJ);


$entityTypesStmt = $db->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type");
$entityTypes = $entityTypesStmt->fetchAll(\PDO::FETCH_COLUMN);


$usersStmt = $db->query("SELECT DISTINCT u.id, u.firstname, u.lastname, u.email 
                         FROM users u 
                         INNER JOIN audit_log al ON al.user_id = u.id 
                         ORDER BY u.lastname, u.firstname");
$users = $usersStmt->fetchAll(\PDO::FETCH_ASSOC);
?>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-info" onclick="exportAuditLogs()">
                <i class="fas fa-download"></i> Exporteren
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="search-filter-bar">
        <form method="GET" action="?tab=auditlogs" class="filter-form">
            <input type="hidden" name="tab" value="auditlogs">
            
            <div class="filter-row">
                <div class="filter-item">
                    <label for="entity_type"><i class="fas fa-tag"></i> Entiteit Type</label>
                    <select id="entity_type" name="entity_type" class="form-select">
                        <option value="">Alle types</option>
                        <?php foreach ($entityTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $entityType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label for="action"><i class="fas fa-bolt"></i> Actie</label>
                    <input type="text" id="action" name="action" class="form-control" 
                        placeholder="Zoek actie..." value="<?= htmlspecialchars($action) ?>">
                </div>
                
                <div class="filter-item">
                    <label for="user_id"><i class="fas fa-user"></i> Gebruiker</label>
                    <select id="user_id" name="user_id" class="form-select">
                        <option value="">Alle gebruikers</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $userId == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label for="date_from"><i class="fas fa-calendar"></i> Van</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" 
                        value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                
                <div class="filter-item">
                    <label for="date_to"><i class="fas fa-calendar"></i> Tot</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" 
                        value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filteren
                    </button>
                    <a href="?tab=auditlogs" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fa fa-clock"></i> Datum/Tijd</th>
                    <th><i class="fa fa-user"></i> Gebruiker</th>
                    <th><i class="fa fa-bolt"></i> Actie</th>
                    <th><i class="fa fa-tag"></i> Entiteit</th>
                    <th><i class="fa fa-info"></i> Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-history fa-3x"></i>
                                <h3>Geen logboeken gevonden</h3>
                                <p>Er zijn nog geen acties gelogd</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small><?= date('d/m/Y H:i:s', strtotime($log->created_at)) ?></small>
                            </td>
                            <td>
                                <?php if ($log->user_id): ?>
                                    <strong><?= htmlspecialchars($log->firstname . ' ' . $log->lastname) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($log->email) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Systeem</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($log->action) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($log->entity_type ?? '-') ?></small>
                                <?php if ($log->entity_id): ?>
                                    <br><small class="text-muted">ID: <?= $log->entity_id ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="showLogDetails(<?= $log->id ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Log Details</h2>
            <span class="close" onclick="closeLogModal()">&times;</span>
        </div>
        <div class="modal-body" id="logDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin"></i> Laden...
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeLogModal()">
                <i class="fas fa-times"></i> Sluiten
            </button>
        </div>
    </div>
</div>

<script>
function showLogDetails(logId) {
    fetch(`?ajax=audit&action=fetch&id=${logId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const log = data.data;
                let html = `
                    <dl class="row">
                        <dt class="col-sm-3">Datum/Tijd</dt>
                        <dd class="col-sm-9">${new Date(log.created_at).toLocaleString('nl-NL')}</dd>
                        
                        <dt class="col-sm-3">Gebruiker</dt>
                        <dd class="col-sm-9">${log.user_id ? (log.firstname + ' ' + log.lastname) : 'Systeem'}</dd>
                        
                        <dt class="col-sm-3">Actie</dt>
                        <dd class="col-sm-9"><code>${log.action}</code></dd>
                        
                        <dt class="col-sm-3">Entiteit</dt>
                        <dd class="col-sm-9">${log.entity_type || '-'} ${log.entity_id ? '(ID: ' + log.entity_id + ')' : ''}</dd>
                        
                        <dt class="col-sm-3">IP Adres</dt>
                        <dd class="col-sm-9">${log.ip_address || '-'}</dd>
                    </dl>
                `;
                
                if (log.old_value || log.new_value) {
                    html += '<h5>Wijzigingen:</h5>';
                    if (log.old_value) {
                        html += '<h6>Oude Waarde:</h6><pre class="bg-light p-2">' + JSON.stringify(JSON.parse(log.old_value), null, 2) + '</pre>';
                    }
                    if (log.new_value) {
                        html += '<h6>Nieuwe Waarde:</h6><pre class="bg-light p-2">' + JSON.stringify(JSON.parse(log.new_value), null, 2) + '</pre>';
                    }
                }
                
                document.getElementById('logDetailsContent').innerHTML = html;
                document.getElementById('logDetailsModal').style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showAlert('Fout bij laden details', 'error');
        });
}

function closeLogModal() {
    document.getElementById('logDetailsModal').style.display = 'none';
}

function exportAuditLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '?tab=auditlogs&export=1&' + params.toString();
}


window.onclick = function(event) {
    const modal = document.getElementById('logDetailsModal');
    if (event.target === modal) {
        closeLogModal();
    }
};
</script>

<style>
.filter-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 10px;
}
</style>

