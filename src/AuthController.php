<?php

declare(strict_types=1);

namespace App;

use PDOException;

final class AuthController
{
    public function __construct(private readonly UserRepository $userRepository) {}

    /** @return array{status: int, data: array<string, mixed>} */
    public function registerUser(string $email, string $password): array
    {
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 422, 'data' => ['error' => "Please enter a valid email address."]];
        }
        if (!$this->isValidPassword($password)) {
            return ['status' => 422, 'data' => ['error' => "Please enter a valid password."]];
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);

        try {
            $user_id = $this->userRepository->create($email, $hashedPass);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && ($e->errorInfo[1] ?? null) === 1062) {
                return ['status' => 422, 'data' => ['error' => "Email address is already registered."]];
            }
            throw $e;
        }

        return ['status' => 201, 'data' => ["user_id" => $user_id]];
    }

    /** @return array{status: int, data: array<string, mixed>} */
    public function loginUser(string $email, string $password): array
    {
        if (!$this->isValidPassword($password)) {
            return ['status' => 422, 'data' => ['error' => "Please enter a valid password."]];
        }
        $account = $this->userRepository->findByEmail($email);

        if ($account === null || !password_verify($password, $account["password_hash"])) {
            return ['status' => 401, 'data' => ['error' => "Invalid Credentials"]];
        }

        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to regenerate session');
        }
        $_SESSION["user_id"] = $account["user_id"];

        return ['status' => 200, 'data' => ["user_id" => $account["user_id"]]];
    }

    /** @return array{status: int, data: array<string, mixed>} */
    public static function logoutUser(): array
    {
        $sessionName = session_name();
        if ($sessionName === false) {
            throw new \RuntimeException('Unable to read session name');
        }
        $_SESSION = [];
        if (!session_destroy()) {
            throw new \RuntimeException('Unable to destroy session');
        }
        $params = session_get_cookie_params();
        setcookie($sessionName, '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);

        return ['status' => 200, 'data' => ["success" => true]];
    }

    private function isValidPassword(string $password): bool
    {
        return trim($password) !== '' && strlen($password) <= 72 && !str_contains($password, "\0");
    }
}
