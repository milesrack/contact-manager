<?php

declare(strict_types=1);

namespace App;

use PDOException;

class AuthController
{
    public function __construct(private readonly UserRepository $ur) {}

    public function registerUser(string $email, string $password): void
    {
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid email address.");
            return;
        }
        if (trim($password) === "" || strlen($password) > 72 || str_contains($password, "\0")) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid password.");
            return;
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);

        try {
            $user_id = $this->ur->create($email, $hashedPass);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && ($e->errorInfo[1] ?? null) === 1062) {
                APIAuthUtil::sendResponseCodeError(422, "Email address is already registered.");
                return;
            }
            throw $e;
        }

        APIAuthUtil::sendResponseCodeBody(201, [
            "user_id" => $user_id,
        ]);
    }

    public function loginUser(string $email, string $password): void
    {
        $account = $this->ur->findByEmail($email);

        if ($account === null || !password_verify($password, $account["password_hash"])) {
            APIAuthUtil::sendResponseCodeError(401, "Invalid Credentials");
            return;
        }

        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to regenerate session');
        }
        $_SESSION["user_id"] = $account["user_id"];

        APIAuthUtil::sendResponseCodeBody(200, [
            "user_id" => $account["user_id"],
        ]);
    }

    public static function logoutUser(): void
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

        APIAuthUtil::sendResponseCodeBody(200, [
            "success" => true,
        ]);
    }
}
