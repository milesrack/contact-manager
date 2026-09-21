<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

/** @var array{env: string, url: string} $app */
$app = require dirname(__DIR__) . '/config/app.php';

error_reporting(E_ALL);

if ($app['env'] === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

/** @var Twig\Environment $twig */
$twig = require dirname(__DIR__) . '/config/twig.php';

if (!session_start()) {
    throw new RuntimeException('Unable to start session');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$userId = $_SESSION['user_id'] ?? null;
$authenticated = is_int($userId) && $userId > 0;

if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

if ($path === '/') {
    if (!$authenticated) {
        header('Location: /login', true, 302);
        exit;
    }
    $template = 'contacts/index.html.twig';
} elseif ($path === '/login' || $path === '/register') {
    if ($authenticated) {
        header('Location: /', true, 302);
        exit;
    }
    $template = $path === '/login' ? 'auth/login.html.twig' : 'auth/register.html.twig';
} else {
    http_response_code(404);
    $template = 'errors/404.html.twig';
}

header('Content-Type: text/html; charset=utf-8');
echo $twig->render($template);
