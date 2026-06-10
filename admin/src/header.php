<?php

use App\Core\Authentication;
use App\Core\Config;
use App\Core\NotificationManager;
use App\Core\TabManager;
use App\Models\Permissions;

$tabManager = new TabManager();
$Authentication = new Authentication();
$user = $Authentication->user();
$permissionsModel = new Permissions();
$userPermissions = $user ? $permissionsModel->getGroupPermissions($user->user_group_id) : [];
$permissions = array_column($userPermissions, 'name');


$tabs = Config::get("TABS");


$sections = [];
foreach ($tabs as $id => $tab) {
    if (!empty($tab['section']) && empty($tab['internal'])) {
        $sections[$tab['section']][] = [
            'id' => $id,
            'title' => $tab['title'],
            'icon' => $tab['icon'],
            'permission' => $tab['permission'] ?? null
        ];
    }
}


foreach ($sections as $sectionId => $sectionTabs) {
    foreach ($sectionTabs as $tab) {
        $tabManager->addTab($tab['id'], $sectionId, $tab['title'], $tab['icon'], $tab['permission']);
    }
}


if (!isset($_SESSION['alerts'])) {
    $_SESSION['alerts'] = [];
}

$pageTitle = $tabManager->hasTab($tabManager->getActiveTab()) ?
    $tabs[$tabManager->getActiveTab()]['title'] :
    'Dashboard';
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::get("NAME")); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/img/icon.ico">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <!-- Poppins Google Font -->
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.min.js"></script>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom styling -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- ERP JavaScript -->
    <script src="assets/js/main.js"></script>

    <script>
        function dismissAlert(uniqid) {
            fetch('/dismiss_alert.php?uniqid=' + encodeURIComponent(uniqid), {
                    method: 'GET'
                })
                .then(response => response.text())
                .then(data => {
                    
                });
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('a[href]').forEach(function(link) {
                const href = link.getAttribute('href');

                
                
                
                if (
                    href &&
                    !href.includes('#') &&
                    !href.startsWith('javascript:') &&
                    !href.startsWith('mailto:') &&
                    !href.startsWith('tel:')
                ) {
                    link.setAttribute('href', href + '#main-content');
                }
            });
        });
    </script>
</head>

<body>
    <div class="erp-container">
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            <div class="navbar-brand">
                <span class="brand-text"><?= htmlspecialchars(Config::get("NAME")); ?></span>
            </div>

            <div class="navbar-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-info">
                        <i class="fa fa-user-circle"></i>
                        <span><?= htmlspecialchars($user->firstname) . " " . htmlspecialchars($user->lastname); ?></span>
                    </div>
                    <button class="btn btn-outline-light btn-sm" onclick="openLogoutConfirm()">
                        <i class="fa fa-sign-out"></i> Uitloggen
                    </button>
                <?php endif; ?>
            </div>
        </nav>

        <div class="main-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <?php
                NotificationManager::showAlerts(); 

                
                $sections = Config::get('SECTIONS');

                foreach ($sections as $sectionKey => $sectionData):
                    $sectionTabs = array_filter($tabs, function ($tab) use ($sectionKey) {
                        return isset($tab['section']) && $tab['section'] === $sectionKey && !isset($tab['internal']);
                    });

                    if (empty($sectionTabs)) continue;
                ?>
                    <div class="nav-section">
                        <div class="section-header" onclick="toggleSection('<?= $sectionKey; ?>')">
                            <i class="fa <?= $sectionData['icon']; ?>"></i>
                            <span><?= $sectionData['title']; ?></span>
                            <i class="fa fa-chevron-down section-toggle"></i>
                        </div>
                        <ul class="nav-menu section-content" id="section-<?= $sectionKey; ?>">
                            <?php foreach ($sectionTabs as $tab_name => $data):
                                if (empty($data['permission']) || $Authentication->hasPermission($data['permission'])): ?>
                                    <li class="<?= ($tab === $tab_name) ? 'active' : ''; ?>">
                                        <a href="?tab=<?= urlencode($tab_name); ?>#main-content">
                                            <i class="fa <?= htmlspecialchars($data['icon']); ?>"></i>
                                            <span><?= htmlspecialchars($data['title']); ?></span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </aside>

            <!-- Main Content -->
            <main class="main-content" id="main-content">
                <div class="content-header">
                    <h1 class="page-title">
                        <i class="fa <?= htmlspecialchars($tabs[$tabManager->getActiveTab()]['icon'] ?? 'fa-circle'); ?>"></i>
                        <?= htmlspecialchars($tabs[$tabManager->getActiveTab()]['title'] ?? 'Onbekende Pagina'); ?>
                    </h1>
                    <div class="breadcrumb">
                        <span><?= htmlspecialchars($tabManager->getTabSection($tabManager->getActiveTab())); ?></span>
                        <i class="fa fa-chevron-right"></i>
                        <span><?= htmlspecialchars($tabs[$tabManager->getActiveTab()]['title'] ?? 'Onbekende Pagina'); ?></span>
                    </div>
                </div>

                <div class="content-body">
                    <?php
                    $tab_file = __DIR__ . "/tabs/{$tabManager->getActiveTab()}.php";
                    if (file_exists($tab_file)) {
                        include $tab_file;
                    } else {
                        echo '<div class="alert alert-danger">
                                <i class="fa fa-exclamation-triangle"></i>
                                <h4>Pagina niet gevonden</h4>
                                <p>De gevraagde pagina kon niet worden geladen. Controleer of de module correct is geïnstalleerd.</p>
                                <a href="?tab=dashboard" class="btn btn-primary">Terug naar Dashboard</a>
                              </div>';
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLogoutConfirm()">&times;</span>
            <h2><i class="fa fa-sign-out"></i> Uitloggen bevestigen</h2>
            <p>Ben je zeker dat je wilt uitloggen?</p>
            <div class="modal-buttons">
                <button onclick="window.location.href='logout.php'" class="confirm-btn">Ja, afmelden</button>
                <button onclick="closeLogoutConfirm()" class="cancel-btn">Annuleren</button>
            </div>
        </div>
    </div>

    <script>
        
        function toggleSection(sectionId) {
            const section = document.getElementById('section-' + sectionId);
            const toggle = document.querySelector(`[onclick="toggleSection('${sectionId}')"] .section-toggle`);

            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                toggle.style.transform = 'rotate(180deg)';
            } else {
                section.style.display = 'none';
                toggle.style.transform = 'rotate(0deg)';
            }
        }

        
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?= $tabManager->getActiveTab(); ?>';
            const currentSection = '<?= $tabs[$tabManager->getActiveTab()]['section'] ?? 'main'; ?>';

            
            const currentSectionEl = document.getElementById('section-' + currentSection);
            if (currentSectionEl) {
                currentSectionEl.style.display = 'block';
                const toggle = document.querySelector(`[onclick="toggleSection('${currentSection}')"] .section-toggle`);
                if (toggle) {
                    toggle.style.transform = 'rotate(180deg)';
                }
            }
        });

        function openLogoutConfirm() {
            document.getElementById("logoutModal").style.display = "block";
        }

        function closeLogoutConfirm() {
            document.getElementById("logoutModal").style.display = "none";
        }

        
        window.onclick = function(event) {
            if (event.target === document.getElementById('logoutModal')) {
                closeLogoutConfirm();
            }
        };
    </script>

    <?php require 'footer.php'; ?>
</body>

</html>
</script>