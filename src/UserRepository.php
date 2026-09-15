<?php

declare(strict_types=1);

namespace App;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(string $email, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{user_id: int, email: string, password_hash: string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, email, password_hash FROM users WHERE email = :email',
        );
        $statement->execute(['email' => $email]);

        /** @var array{user_id: int|string, email: string, password_hash: string}|false $user */
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return null;
        }

        $user['user_id'] = (int) $user['user_id'];

        return $user;
    }
}
