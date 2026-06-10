<?php
require_once 'src/autoload.php';

use App\Core\Application;
use App\Core\Authentication;



$Authentication = new Authentication();
if (!$Authentication->check()) {
    header('Location: login.php');
    die;
}

Application::callInitialization();


require_once 'src/header.php';
