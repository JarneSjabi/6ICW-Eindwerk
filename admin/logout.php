<?php
require_once 'src/autoload.php';


use App\Core\Authentication;

$Authentication = new Authentication();

$Authentication->logout();

header('Location: login.php');

exit;
