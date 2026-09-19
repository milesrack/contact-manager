<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthApiTest extends TestCase
{
    private PDO $pdo;
    private Client $client;

    /**
     * Emails created during each test so we can clean them up afterward.
     *
     * @var string[]
     */
    private array $testEmails = [];

    protected function setUp(): void
    {
        $this->pdo = Database::connect();

        $this->client = new Client([
            'base_uri' => 'http://localhost:8000',
            'http_errors' => false,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->testEmails as $email) {
            $statement = $this->pdo->prepare('DELETE FROM users WHERE email = :email');

            $statement->execute([
                'email' => $email,
            ]);
        }
    }

    public function testRegisterCreatesUser(): void
    {
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $password = 'test-password';

        $this->testEmails[] = $email;

        $response = $this->client->post('/api/register', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('user_id', $body);
        self::assertGreaterThan(0, $body['user_id']);

        $statement = $this->pdo->prepare('SELECT email, password_hash FROM users WHERE user_id = :user_id');

        $statement->execute([
            'user_id' => $body['user_id'],
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($user);
        self::assertSame($email, $user['email']);
        self::assertTrue(
            password_verify($password, $user['password_hash']),
        );
    }

    public function testLoginWithCorrectCredentials(): void
    {
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $password = 'test-password';

        $userId = $this->createTestUser($email, $password);

        $cookies = new CookieJar();

        $response = $this->client->post('/api/login', [
            'cookies' => $cookies,
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($userId, (int) $body['user_id']);

        self::assertNotEmpty($cookies->toArray());
    }

    public function testLoginWithInvalidPassword(): void
    {
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $password = 'correct-password';

        $this->createTestUser($email, $password);

        $response = $this->client->post('/api/login', [
            'json' => [
                'email' => $email,
                'password' => 'wrong-password',
            ],
        ]);

        self::assertSame(401, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Invalid Credentials', $body['error']);
    }

    public function testLogoutDestroysSession(): void
    {
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $password = 'test-password';

        $this->createTestUser($email, $password);

        $cookies = new CookieJar();

        $loginResponse = $this->client->post('/api/login', [
            'cookies' => $cookies,
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        self::assertSame(200, $loginResponse->getStatusCode());
        self::assertNotEmpty($cookies->toArray());

        $logoutResponse = $this->client->post('/api/logout', [
            'cookies' => $cookies,
        ]);

        self::assertSame(200, $logoutResponse->getStatusCode());

        $body = json_decode((string) $logoutResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($body['success']);
    }

    // helper func
    private function createTestUser(string $email, string $password): int
    {
        $statement = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)');

        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash(
                $password,
                PASSWORD_DEFAULT,
            ),
        ]);

        $userId = (int) $this->pdo->lastInsertId();

        $this->testEmails[] = $email;

        return $userId;
    }
}
