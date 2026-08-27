<?php

declare(strict_types=1);

$getEnv = static function (string $key): string {
    $value = $_ENV[$key] ?? getenv($key);

    if (!is_string($value) || $value === '') {
        throw new \RuntimeException("Missing application configuration: {$key}");
    }

    return $value;
};

$env = $getEnv('APP_ENV');

if (!in_array($env, ['development', 'test', 'production'], true)) {
    throw new \RuntimeException('APP_ENV must be development, test, or production.');
}

return [
    'env' => $env,
    'url' => $getEnv('APP_URL'),
];
