<?php

use App\Core\NotificationManager;
use App\Core\Request;

require_once 'src/autoload.php';
require_once 'src/header.php';

if (isset($_GET["uniqid"])) {
    NotificationManager::dismissAlert(htmlspecialchars(Request::get('uniqid')));
}
