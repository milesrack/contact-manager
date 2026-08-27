<?php

declare(strict_types=1);

$getEnv = static function (string $key): string {
    $value = $_ENV[$key] ?? getenv($key);

    if (!is_string($value) || $value === '') {
        throw new \RuntimeException("Missing database configuration: {$key}");
    }

    return $value;
};

$port = $getEnv('DB_PORT');

if (!ctype_digit($port)) {
    throw new \RuntimeException('DB_PORT must be an integer.');
}

$port = (int) $port;

if ($port < 1 || $port > 65535) {
    throw new \RuntimeException('DB_PORT must be between 1 and 65535.');
}

return [
    'host' => $getEnv('DB_HOST'),
    'port' => $port,
    'name' => $getEnv('DB_NAME'),
    'user' => $getEnv('DB_USER'),
    'password' => $getEnv('DB_PASSWORD'),
];
