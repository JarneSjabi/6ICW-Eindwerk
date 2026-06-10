<?php

use App\Core\Utils;
use App\Controllers\UserGroupController;
use App\Models\Permissions;
use App\Models\UserGroup;


$controller = new UserGroupController();
$index = $controller->index();
$data = $index["data"];

$userGroups = $data['records'] ?? [];
$totalGroups = $data['total'] ?? 0;
$currentPage = $data['page'] ?? 1;
$totalPages = $data['totalPages'] ?? 1;
$search = $data['search'] ?? '';
$permissionsModel = new Permissions();
$allPermissions = $permissionsModel->getAllPermissions();
?>

<script src="/assets/js/user_groups.js"></script>

<div class="tab-content">
    <div class="tab-header">
        <div class="tab-actions">
            <button class="btn btn-success" onclick="openCreateModal()">
                <i class="fa fa-plus"></i> Nieuwe Rol
            </button>
            <button class="btn btn-info" onclick="exportGroups()">
                <i class="fas fa-download"></i> Exporteren
            </button>
        </div>
    </div>

    <div class="roles-container">
        <?php foreach ($userGroups as $userGroup):
            $userGroupPermissions = $permissionsModel->getGroupPermissions($userGroup['id']);
        ?>
            <div class="role-card">
                <div class="role-header">
                    <h3><?= htmlspecialchars($userGroup['name']) ?></h3>
                    <div class="role-actions">
                        <button onclick="editGroup(<?= $userGroup['id'] ?>)" class="btn btn-sm btn-info">
                            <i class="fa fa-edit"></i>
                        </button>
                        <button onclick="confirmDelete(<?= $userGroup['id'] ?>, '<?= htmlspecialchars($userGroup['name']) ?>')" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
                <p class="role-description"><?= htmlspecialchars($userGroup['description']) ?></p>

                <div class="permissions-grid">
                    <?php foreach ($allPermissions as $perm):
                        $hasPerm = false;
                        foreach ($userGroupPermissions as $rp) {
                            if ($rp['id'] == $perm['id'] && $rp['value'] == 1) {
                                $hasPerm = true;
                                break;
                            }
                        }
                    ?>
                        <div class="permission-item <?= $hasPerm ? 'has-permission' : '' ?>">
                            <i class="fa fa-<?= $hasPerm ? 'check-circle' : 'times-circle' ?>"></i>
                            <span><?= htmlspecialchars($perm['description']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>


    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle text-danger"></i> Verwijderen bevestigen</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Weet u zeker dat u de rol "<strong id="deleteGroupName"></strong>" wilt verwijderen?</p>
                <p class="text-danger"><small><i class="fas fa-info-circle"></i> Deze actie kan niet ongedaan worden gemaakt.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Annuleren
                </button>
                <button type="button" class="btn btn-danger" onclick="deleteGroup()">
                    <i class="fas fa-trash"></i> Verwijderen
                </button>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="groupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-user-shield"></i> Nieuwe Rol</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="groupForm">
                <input type="hidden" id="groupId" name="id">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-tag"></i> Naam *</label>
                            <input type="text" id="name" name="name" required maxlength="255"
                                placeholder="Bijv. Beheerder, Teamleider, Bediende...">
                            <div class="invalid-feedback" id="nameError"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Beschrijving</label>
                        <textarea id="description" name="description" rows="3" maxlength="1000"
                            placeholder="Optionele beschrijving van de rol..."></textarea>
                    </div>

                    <div class="form-group">
                        <h3><i class="fa fa-cog"></i> Permissies</h3>
                        <p>Sterren tonen hoeveel risico je in het algemeen neemt als je het inschakelt. Zorg ervoor dat je risicovolle permissies enkel toekent aan gebruikers die je voldoende vertrouwt.</p>
                        <div class="edit-permissions-grid">
                            <div class="edit-permission-item template" style="display:none;">
                                <label class="permission-toggle">
                                    <input type="checkbox" id="perm_PERMISSION_ID" name="perm_PERMISSION_ID">
                                    <span class="toggle-slider"></span>
                                </label>
                                <label for="perm_PERMISSION_ID">
                                    <small class="perm-desc">PERMISSION_DESCRIPTION</small>
                                    <span class="risk-stars" title="Risicograad: PERMISSION_RISK_GRADE">

                                    </span>
                                </label>
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



    <style>
        .roles-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .role-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .role-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .role-description {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .permission-item {
            padding: 8px;
            border-radius: 4px;
            background: #f5f5f5;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .permission-item.has-permission {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .permission-item i.fa-check-circle {
            color: #4caf50;
        }

        .permission-item i.fa-times-circle {
            color: #f44336;
        }
    </style>
</div>