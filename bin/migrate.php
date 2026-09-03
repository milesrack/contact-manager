<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/config/bootstrap.php';

$root = dirname(__DIR__);
$pdo = Database::connect();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
);

$migrations = glob($root . '/database/migrations/*.sql');
if ($migrations === false) {
    throw new \RuntimeException('Failed to read the migrations directory.');
}

sort($migrations, SORT_STRING);

$exists = $pdo->prepare(
    'SELECT COUNT(*) FROM schema_migrations WHERE migration = ?',
);

$record = $pdo->prepare(
    'INSERT INTO schema_migrations (migration, applied_at) VALUES (?, CURRENT_TIMESTAMP)',
);

foreach ($migrations as $migration) {
    $filename = basename($migration);
    $exists->execute([$filename]);
    if ((int) $exists->fetchColumn() === 0) {
        $sql = file_get_contents($migration);
        if ($sql === false) {
            throw new \RuntimeException("Failed to read migration file: $migration");
        }
        $pdo->exec($sql);
        $record->execute([$filename]);
        echo "Migration applied: $filename\n";
    }
}
