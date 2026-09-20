<?php

namespace App;

use JsonException;

class AuthController
{
    public function __construct(private readonly UserRepository $ur) {}

    /**
     * @throws JsonException
     */
    public function registerUser(string $email, string $password): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid email address.");
            return;
        }
        if (trim($password) === "") {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid password.");
            return;
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);

        $user_id = $this->ur->create($email, $hashedPass);

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

        session_start();
        session_regenerate_id(true);
        $_SESSION["user_id"] = $account["user_id"];

        APIAuthUtil::sendResponseCodeBody(200, [
            "user_id" => $account["user_id"],
        ]);
    }

    public function logoutUser(): void
    {
        session_start();
        $_SESSION = [];
        session_destroy();

        APIAuthUtil::sendResponseCodeBody(200, [
            "success" => true,
        ]);
    }
}
