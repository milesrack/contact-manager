<?php

declare(strict_types=1);

require_once __DIR__ . "/../../vendor/autoload.php";

use App\ApiUtil\APIAuthUtil;
use App\AuthController;
use App\UserRepository;
use App\Database;

$pdo = Database::connect();
$userRepository = new UserRepository($pdo);
$authController = new AuthController($userRepository);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


if ($path === '/api/register') {
    if ($method !== 'POST') {
        \App\ApiUtil\APIAuthUtil::sendResponseCodeError(405, "Method not allowed");
        return;
    }

    $contents = \App\ApiUtil\APIAuthUtil::grabAndValidateCredentials();

    if ($contents === null) {
        return;
    }


    $authController->registerUser($contents['email'], $contents['password']);

} elseif ($path === '/api/login') {
    if ($method !== 'POST') {
        \App\ApiUtil\APIAuthUtil::sendResponseCodeError(405, "Method not allowed");
        return;
    }

    $contents = \App\ApiUtil\APIAuthUtil::grabAndValidateCredentials();

    if ($contents === null) {
        return;
    }

    $authController->loginUser($contents['email'], $contents['password']);

} elseif ($path === '/api/logout') {
    if ($method !== 'POST') {
        \App\ApiUtil\APIAuthUtil::sendResponseCodeError(405, "Method not allowed");
        return;
    }

    $authController->logoutUser();

} else {
    APIAuthUtil::sendResponseCodeError(404, "Error");
}
