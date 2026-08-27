<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testDatabaseConnection(): void
    {
        $statement = Database::connect()->query('SELECT 1');

        if ($statement === false) {
            self::fail('Database query failed.');
        }

        self::assertSame(1, (int) $statement->fetchColumn());
    }
}
