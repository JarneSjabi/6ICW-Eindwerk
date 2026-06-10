<?php

use App\Core\Utils;
use App\Core\Controller;
use App\Models\User;
use App\Controllers\UserController;
use App\Controllers\UserGroupController;
use App\Models\UserGroup;



$userController = new UserController();
$userIndex = $userController->index();
$userData = $userIndex["data"];

$users = $userData['records'] ?? [];
$totalUsers = $userData['total'] ?? 0;
$currentPage = $userData['page'] ?? 1;
$totalPages = $userData['totalPages'] ?? 1;
$search = $userData['search'] ?? '';
$stats = $userData['stats'] ?? (object)[
    'user_count' => 0,
    'active_user_count' => 0
];



$ugController = new UserGroupController(Controller::SECONDARY_MODE);
$ugIndex = $ugController->index();
$ugData = $ugIndex["data"];

$userGroups = $ugData['records'] ?? [];
$totalUserGroups = $ugData['total'] ?? 0;
?>

<script src="/assets/js/user.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal()">
                <i class="fa fa-user-plus"></i> Nieuwe Gebruiker
            </button>
            <button class="btn btn-info" onclick="exportUsers()">
                <i class="fas fa-download"></i> Exporteren
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-card-users">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats->user_count) ?></h3>
                <p>Totale gebruikers</p>
            </div>
        </div>
        <div class="stat-card stat-card-active">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats->active_user_count) ?></h3>
                <p>Actieve gebruikers</p>
            </div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Zoek gebruikers..."
                value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
            <button onclick="performSearch()" class="btn btn-primary">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="filter-actions">
            <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
                <tr>
                    <th><i class="fa fa-tag"></i> Naam</th>
                    <th><i class="fa fa-at"></i> E-mail</th>
                    <th><i class="fa fa-history"></i> Laatste aanmelding</th>
                    <th><i class="fa fa-user-shield"></i> Rol</th>
                    <th><i class="fa fa-toggle-on"></i> Status</th>
                    <th><i class="fa fa-clock"></i> Aangemaakt op</th>
                    <th><i class="fa fa-clock"></i> Bijgewerkt op</th>
                    <th><i class="fa fa-hammer"></i> Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="<?= $search ? 'fas fa-filter-circle-xmark' : 'fas fa-folder-open fa-3x' ?>"></i>
                                <h3><?= $search ? 'Geen zoekresultaten' : 'Geen gebruikers gevonden' ?></h3>
                                <p><?= $search ? 'Probeer een andere zoekterm' : 'Voeg je eerste gebruiker toe om te beginnen' ?></p>
                                <?php if (!$search): ?>
                                    <button class="btn btn-primary" onclick="openCreateModal()">
                                        <i class="fas fa-user-plus"></i> Nieuwe Gebruiker
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?= $user['id'] ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-icon">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                    <div>
                                        <b><?= htmlspecialchars($user['firstname']) . ' ' . htmlspecialchars($user['lastname']) ?></b>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['last_login']) ?></td>
                            <td>
                                <a href="?tab=user_groups&open_edit_id=<?= $user['user_group_id'] ?>" class="text-primary" title="Bekijk rol">
                                    <?= UserGroup::find($user['user_group_id'])->name; ?>
                                </a>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                    <i class="fas fa-<?= $user['is_active'] ? 'check' : 'times' ?>"></i>
                                    <?= $user['is_active'] ? 'Actief' : 'Inactief' ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['created_at'] ?? '-') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['updated_at'] ?? '-') ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info" onclick="viewUser(<?= $user['id'] ?>)" title="Bekijken">
                                        <i class="fas fa-eye"></i> Detailweergave
                                    </button>
                                    <button class="btn btn-warning" onclick="editUser(<?= $user['id'] ?>)" title="Bewerken">
                                        <i class="fas fa-edit"></i> Bewerken
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname']) . ' ' . htmlspecialchars($user['lastname']) ?>')" title="Verwijderen">
                                        <i class="fas fa-trash"></i> Verwijderen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?tab=users&page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <a href="?tab=users&page=<?= $i ?>&search=<?= urlencode($search) ?>"
                    class="page-link <?= $i == $currentPage ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?tab=users&page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Nieuwe Gebruiker</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="userForm">
            <input type="hidden" id="userId" name="id">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname"><i class="fas fa-tag"></i> Voornaam *</label>
                        <input type="text" id="firstname" name="firstname" required maxlength="255"
                            placeholder="Bijv. Jan, Sam, ...">
                        <div class="invalid-feedback" id="firstnameError"></div>
                    </div>
                    <div class="form-group">
                        <label for="lastname"><i class="fas fa-tag"></i> Familienaam *</label>
                        <input type="text" id="lastname" name="lastname" required maxlength="255"
                            placeholder="Bijv. Verhasselt, Hillewaere, ...">
                        <div class="invalid-feedback" id="lastnameError"></div>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-at"></i> E-mail *</label>
                        <input type="text" id="email" name="email" required maxlength="255"
                            placeholder="Bijv. daan.terheyde@gmail.com, ...">
                        <div class="invalid-feedback" id="emailError"></div>
                    </div>
                    <div class="form-group">
                        <label for="user_group_id"><i class="fas fa-user-shield"></i> Rol *</label>
                        <select id="user_group_id" name="user_group_id" class="form-select" required>
                            <?php
                            if (count($userGroups) === 0) {
                                echo "<option value=''>Gelieve eerst een rol aan te maken! Reden: Geen groepen beschikbaar.</option>";
                            } else {
                                foreach ($userGroups as $ug) {
                                    $id = $ug['id'];
                                    $description = $ug['description'];
                                    $name = $ug['name'];
                                    echo "<option value='$id'>$name</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Password Reset Section (only when editing) -->
                <div id="passwordResetSection" style="display: none;">
                    <hr>
                    <h4><i class="fas fa-key"></i> Wachtwoord Reset</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> Nieuw Wachtwoord</label>
                            <input type="password" id="new_password" name="new_password" 
                                placeholder="Laat leeg om niet te wijzigen" minlength="8">
                            <small class="text-muted">Minimaal 8 karakters. Laat leeg om huidige wachtwoord te behouden.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Bevestig Wachtwoord</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                placeholder="Bevestig nieuw wachtwoord">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Annuleren
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn" onclick="handleFormSubmit(event)">
                    <i class="fas fa-save"></i> Opslaan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle text-danger"></i> Potentieel Gevaarlijke Actie</h2>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Weet u zeker dat u de gebruiker "<strong id="deleteUserName"></strong>" wilt verwijderen?</p>
            <p class="text-danger"><small><i class="fas fa-info-circle"></i> Deze actie kan niet ongedaan worden gemaakt.</small></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Annuleren
            </button>
            <button type="button" class="btn btn-danger" onclick="deleteUser()">
                <i class="fas fa-trash"></i> Verwijderen
            </button>
        </div>
    </div>
</div>