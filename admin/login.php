<?php
require_once 'src/autoload.php';


use App\Core\Authentication;
use App\Core\Config;
use App\Core\Request;
use App\Core\Database;
use App\Core\Application;
use App\Core\NotificationManager;

$db = Database::getConnection();
$Authentication = new Authentication();

$show_login = false;
if (isset($_GET['register']) && $_GET['register'] == '1') {
    header('Location: register.php');
    exit;
}

if (isset($_POST['show_login']) || isset($_GET['msg'])) {
    $show_login = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['login'])) {
        $username = strtolower(htmlspecialchars($_POST['username']));
        $password = htmlspecialchars($_POST['password']);
        $remember = Request::post('remember', null);

        $attempt = $Authentication->attempt($username, $password, isset($remember));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(Config::get("NAME")); ?></title>
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

    <style>
        :root {
            
            --primary-color: #1862ac;
            --primary-light: #3c8ee0;
            --primary-dark: #145595;
            --primary-transparent: rgba(139, 0, 0, 0.1);

            
            --secondary-color: #718898;
            --secondary-light: #7891a3;
            --secondary-dark: #485965;

            
            --accent-color: #00ff95;
            
            --accent-light: #3bff7c;
            --accent-dark: #53b80b;
            --accent-hover: #48ff00;
            

            
            --success-color: #28a745;
            
            --success-light: #34ce57;
            --success-dark: #1e7e34;
            --info-color: #17a2b8;
            
            --info-light: #1fc8e3;
            --info-dark: #117a8b;
            --warning-color: #ea861b;
            
            --warning-light: #f19736;
            --warning-dark: rgb(199, 121, 12);
            --danger-color: #dc3545;
            
            --danger-light: #e4606d;
            --danger-dark: #a71e2a;

            
            --bg-primary: #f8f9fa;
            
            --bg-secondary: #e9ecef;
            
            --bg-tertiary: #dee2e6;
            
            --bg-card: #ffffff;
            
            --bg-sidebar: #1862ac;
            --bg-navbar: #1862ac;
            --bg-hover: rgba(0, 104, 139, 0.05);

            
            --text-primary: #2c3e50;
            
            --text-secondary: #495057;
            
            --text-light: #ffffff;
            
            --text-muted: #6c757d;
            
            --text-on-primary: #ffffff;
            

            
            --border-color: #ced4da;
            
            --border-light: #e9ecef;
            --border-dark: #adb5bd;
            --border-focus: #1862ac;
            --border-sidebar: #145595;

            
            --shadow-color: rgba(0, 104, 139, 0.1);
            --shadow-sm: 0 0.125rem 0.25rem var(--shadow-color);
            --shadow: 0 0.5rem 1rem var(--shadow-color);
            --shadow-lg: 0 1rem 3rem var(--shadow-color);
            --shadow-focus: 0 0 0 0.2rem rgba(0, 104, 139, 0.25);

            
            --btn-primary: var(--primary-color);
            --btn-primary-hover: var(--primary-dark);
            --btn-secondary: var(--secondary-color);
            --btn-secondary-hover: var(--secondary-dark);
            --btn-success: var(--success-color);
            --btn-success-hover: var(--success-dark);
            --btn-info: var(--info-color);
            --btn-info-hover: var(--info-dark);
            --btn-warning: var(--warning-color);
            --btn-warning-hover: var(--warning-dark);
            --btn-danger: var(--danger-color);
            --btn-danger-hover: var(--danger-dark);

            
            --gradient-primary: linear-gradient(135deg,
                    var(--primary-color) 0%,
                    var(--primary-dark) 100%);
            --gradient-secondary: linear-gradient(135deg,
                    var(--secondary-color) 0%,
                    var(--secondary-dark) 100%);
            --gradient-accent: linear-gradient(135deg,
                    var(--accent-color) 0%,
                    var(--accent-dark) 100%);

            
            --border-radius: 8px;
            --border-radius-sm: 4px;
            --border-radius-lg: 12px;
            --transition: all 0.3s ease;
            --transition-fast: all 0.15s ease;
            --transition-slow: all 0.5s ease;
        }
    
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;

            background: linear-gradient(-45deg,
                    var(--primary-color),
                    var(--primary-light),
                    var(--primary-dark),
                    var(--primary-light));
            background-size: 400% 400%;
            animation: gradientWave 8s ease infinite;
            
            background-image: url('/assets/img/call.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;

            color: whitesmoke;
            text-align: center;
        }

        @keyframes gradientWave {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .main-container {
            animation: fadeInUp 0.8s ease-out;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .choice-container {
            display: flex;
            gap: 40px;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            justify-content: center;
        }

        .choice-box {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 16px;
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
            box-shadow:
                0 8px 25px rgba(139, 0, 0, 0.15),
                0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 40px 25px;
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            cursor: pointer;
        }

        .choice-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.4),
                    transparent);
            transition: left 0.6s ease;
        }

        .choice-box:hover::before {
            left: 100%;
        }

        .choice-box:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--primary-light);
            box-shadow:
                0 15px 35px rgba(139, 0, 0, 0.25),
                0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .choice-box:active {
            transform: translateY(-4px) scale(1.01);
        }

        .choice-box h2 {
            color: var(--primary-light);
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .choice-box:hover h2 {
            color: var(--primary-dark);
            transform: scale(1.05);
        }

        .choice-box h2 i {
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .choice-box:hover h2 i {
            transform: scale(1.2);
            color: var(--gold-accent);
        }

        .choice-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            font-size: 18px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(139, 0, 0, 0.3);
        }

        .choice-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.2),
                    transparent);
            transition: left 0.5s ease;
        }

        .choice-btn:hover::before {
            left: 100%;
        }

        .choice-btn:hover {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.4);
        }

        .choice-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(139, 0, 0, 0.3);
        }

        .choice-btn i {
            margin-right: 8px;
            transition: transform 0.3s ease;
        }

        .choice-btn:hover i {
            transform: scale(1.1);
        }

        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(139, 0, 0, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(139, 0, 0, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(139, 0, 0, 0);
            }
        }

        .choice-box:nth-child(1) {
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        .choice-box:nth-child(2) {
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .login-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 100%;
            max-width: 400px;
            margin: 0 auto !important;
            text-align: center;
            color: var(--primary-light);
        }

        .title {
            margin-bottom: 20px;
            background-color: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            color: white;
        }

        .input-field {
            width: 95%;
            padding: 14px;
            text-align: center;
            font-size: 18px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--btn-primary);
            font-size: 18px;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            transition-duration: 0.4s;
        }

        .submit-btn:hover {
            background-color: var(--btn-primary-hover);
        }

        .theme-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }

        input:checked+.slider {
            background-color: #2196F3;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        @media (max-width: 600px) {
            body {
                background-position: top center;
                background-size: contain;
                height: auto;
                padding: 20px 10px;
                display: block;
            }

            .main-container {
                margin-top: 20px;
                text-align: center;
            }

            h1 {
                font-size: 1.8rem !important;
                margin-bottom: 20px;
            }

            .choice-container {
                flex-direction: column;
                gap: 20px;
                max-width: 100%;
            }

            .choice-box {
                width: 100% !important;
                padding: 25px 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            }

            .choice-box:hover {
                transform: translateY(-5px) scale(1.01);
            }

            .choice-box h2 {
                font-size: 1.4rem;
            }

            .choice-btn {
                font-size: 16px;
                padding: 14px;
            }

            .login-container {
                width: 100% !important;
                padding: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                margin: 10px auto !important;
            }

            .input-field {
                font-size: 16px;
                padding: 12px;
                width: 100%;
            }

            .submit-btn {
                font-size: 16px;
                padding: 12px;
            }

            .alerts {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <?php if (!$show_login): ?>
        <div class="main-container">
            <!-- <img src="/assets/img/banner.png" style="width:100%;"> -->
            <h1 class="title"><?= Config::get('NAME'); ?></h1>
            <h3 class="title"><?= htmlspecialchars(Application::generateQuote()); ?></h3>
            <div class="choice-container">
                <div class="choice-box">
                    <h2><i class="fa fa-sign-in-alt"></i> Aanmelden</h2>
                    <form method="post">
                        <button type="submit" name="show_login" class="choice-btn"><i class="fa fa-sign-in"></i> Inloggen</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($show_login): ?>
        <div class="login-container">
            <div class="icon">
                <i class="fas fa-lock"></i>
            </div>

            <!-- <h1><img src="/assets/img/banner.png" width="150px"> <?= Config::get("NAME"); ?></h1> -->

            <hr>

            <h2>Inloggen</h2>

            <hr>

            <?php
            NotificationManager::showAlerts(); 
            echo "<i>''" . Application::generateQuote() . "''</i><br><br>"; 
            ?>

            <a href="index.php" class="btn"><i class="fa fa-arrow-left"></i> Terug</a>
            <br><br>

            <div class="alerts">
                <?php
                if (isset($_GET['msg'])) {
                    if ($_GET['msg'] == 'userfail') {
                        echo "<div class='alert alert-warning shadow-sm p-3 rounded text-start'>
                    <h5 class='alert-heading mb-1'><i class='fa fa-exclamation-triangle'></i> Login mislukt</h5>
                    <small>Gebruiker is ongeldig of het wachtwoord klopt niet.</small>
                  </div>";
                    } elseif ($_GET['msg'] == 'maintenance') {
                        echo "<div class='alert alert-danger shadow-sm p-3 rounded text-start'>
                    <h5 class='alert-heading mb-1'><i class='fa fa-tools'></i> Onderhoudsmodus actief</h5>
                    <small>Probeer het later opnieuw.</small>
                  </div>";
                    } elseif ($_GET['msg'] == 'toomany') {
                        echo "<div class='alert alert-warning shadow-sm p-3 rounded text-start'>
                    <h5 class='alert-heading mb-1'><i class='fa fa-ban'></i> Te veel inlogpogingen</h5>
                    <small>U bent geblokkeerd. Probeer het later opnieuw.</small>
                  </div>";
                    }
                }
                ?>
            </div>

            <form method="post" autocomplete="off">
                <label for="username"><i class="fa fa-user"></i> Gebruiker</label>
                <br>
                <i>(voornaam + achternaam of e-mail)</i>
                <input type="text" name="username" id="username" class="input-field" placeholder="E-mail of volledige naam" required>
                <br><br>
                <label for="password"><i class="fa fa-key"></i> Wachtwoord</label>
                <input type="password" name="password" id="password" class="input-field" placeholder="Wachtwoord" required>
                <br><br>
                <label class="2toggle">
                    <input type="checkbox" id="remember" name="remember">
                </label>
                <label for="remember">
                    <small>Aangemeld blijven</small>
                </label>
                <br><br>
                <button type="submit" class="submit-btn" name="login"><i class="fas fa-sign-in-alt"></i> Login</button>
                <br>
            </form>
        </div>
    <?php endif; ?>
</body>

</html>