<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        /** @var array{host: string, port: int, name: string, user: string, password: string} $config */
        $config = require dirname(__DIR__) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name'],
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
