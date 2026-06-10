<?php

use App\Core\Utils;
use App\Core\Request;
use App\Core\Controller;
use App\Controllers\UserController;
use App\Controllers\UserGroupController;

$entityType = 'App\\Models\\User';


$userId = Request::get('id');
$controller = new UserController();
$resp = $controller->show($userId);
if (!$resp['success']) {
    echo "<div class='alert alert-danger'>Gebruiker niet gevonden</div>";
    return;
}
$user = $resp['data'];



$ugc = new UserGroupController(Controller::SECONDARY_MODE);
$group = null;
if (!empty($user['user_group_id'])) {
    $gresp = $ugc->show($user['user_group_id']);
    if ($gresp['success']) $group = $gresp['data'];
}
?>

<script src="/assets/js/user.js"></script>

<div class="tab-content detail-view">
    <div class="detail-header">
        <h2><i class="fas fa-user-circle"></i> Gebruiker: <?= htmlspecialchars($user['firstname']) . ' ' . htmlspecialchars($user['lastname']) ?></h2>
        <div class="detail-actions">
            <a href="?tab=users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Terug</a>
            <a class="btn btn-warning" href="?tab=users&open_edit_id=<?= $user['id'] ?>"><i class="fas fa-edit"></i> Bewerken</a>
            <button class="btn btn-danger" onclick="confirmEdit(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname']) . ' ' . htmlspecialchars($user['lastname']) ?>')" title="Verwijderen">
                <i class="fas fa-trash"></i> Verwijderen
            </button>
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-main">
            <div class="detail-section info-section card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fa fa-info-circle"></i> Informatie</h3>
                </div>
                <div class="card-body">
                    <table class="detail-table table table-hover">
                        <tr>
                            <th style="width: 200px"><i class="fas fa-tag"></i> Voornaam</th>
                            <td><?= htmlspecialchars($user['firstname']) ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-tag"></i> Familienaam</th>
                            <td><?= htmlspecialchars($user['lastname']) ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-at"></i> E-mail</th>
                            <td><?= nl2br(htmlspecialchars($user['email'])) ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-user-shield"></i> Rol</th>
                            <td>
                                <a href="?tab=user_groups&open_edit_id=<?= $user['user_group_id'] ?>" class="badge bg-info text-decoration-none">
                                    <?= $group ? htmlspecialchars($group['name']) : '-' ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-toggle-<?= $user['is_active'] ? 'on' : 'off' ?>"></i> Status</th>
                            <td>
                                <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $user['is_active'] ? 'Actief' : 'Inactief' ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="detail-sidebar">
            <div class="detail-section changelog-section card">
                <div class="card-header bg-secondary text-white">
                    <h3 class="mb-0"><i class="fa fa-history"></i> Logboek</h3>
                </div>
                <div class="card-body p-0">
                    <?= Utils::renderChangelog($entityType, $user['id']); ?>
                </div>
            </div>
        </div>
    </div>
</div>