<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use App\UserRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $users;
    private string $email;

    protected function setUp(): void
    {
        $this->pdo = Database::connect();
        $this->pdo->beginTransaction();
        $this->users = new UserRepository($this->pdo);
        $this->email = bin2hex(random_bytes(16)) . '@example.com';
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testCreatePersistsUserAndReturnsId(): void
    {
        $passwordHash = password_hash('test-password', PASSWORD_DEFAULT);
        $userId = $this->users->create($this->email, $passwordHash);

        self::assertGreaterThan(0, $userId);

        $statement = $this->pdo->prepare(
            'SELECT email, password_hash FROM users WHERE user_id = :user_id',
        );
        $statement->execute(['user_id' => $userId]);

        self::assertSame([
            'email' => $this->email,
            'password_hash' => $passwordHash,
        ], $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testFindByEmailReturnsUser(): void
    {
        $passwordHash = password_hash('test-password', PASSWORD_DEFAULT);
        $userId = $this->users->create($this->email, $passwordHash);

        self::assertSame([
            'user_id' => $userId,
            'email' => $this->email,
            'password_hash' => $passwordHash,
        ], $this->users->findByEmail($this->email));
    }

    public function testFindByEmailReturnsNullForMissingUser(): void
    {
        self::assertNull($this->users->findByEmail($this->email));
    }

    public function testCreateDuplicateEmailPreservesDatabaseErrorAndOriginalUser(): void
    {
        $passwordHash = password_hash('original-password', PASSWORD_DEFAULT);
        $userId = $this->users->create($this->email, $passwordHash);

        try {
            $this->users->create($this->email, password_hash('different-password', PASSWORD_DEFAULT));
            self::fail('Creating a duplicate email should fail.');
        } catch (PDOException $exception) {
            self::assertSame('23000', $exception->getCode());
            self::assertSame(1062, $exception->errorInfo[1] ?? null);
        }

        self::assertSame([
            'user_id' => $userId,
            'email' => $this->email,
            'password_hash' => $passwordHash,
        ], $this->users->findByEmail($this->email));
    }
}
