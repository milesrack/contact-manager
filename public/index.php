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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Manager</title>
</head>
<body>
    <main>
        <h1>Contact Manager</h1>
    </main>
</body>
</html>
