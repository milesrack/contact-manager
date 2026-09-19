<?php

namespace App\Auth;

use App\UserRepository;
use JsonException;

class AuthController
{
    public function __construct(private readonly UserRepository $ur) {}

    /**
     * @throws JsonException
     */
    public function registerUser(): void {
        $creds = APIAuthUtil::grabAndValidateCredentials();

        if($creds === null) return;

        $user = $creds["email"];
        $pass = $creds["password"];

        $hashedPass = password_hash($pass, PASSWORD_DEFAULT);

        $user_id = $this->ur->create($user, $hashedPass);

        APIAuthUtil::sendResponseCodeBody(201, [
            "user_id" => $user_id
        ]);
    }

    public function loginUser(): void {
        $creds = APIAuthUtil::grabAndValidateCredentials();

        if($creds === null) return;

        $user = $creds["email"];
        $pass = $creds["password"];

        $account = $this->ur->findByEmail($user);
        if($account === null || !password_verify($pass, $account["password_hash"])) {
            APIAuthUtil::sendResponseCodeError(401, "Invalid Credentials");
            return;
        }

        session_start();
        session_regenerate_id(true);
        $_SESSION["user_id"] = $account["user_id"];

        APIAuthUtil::sendResponseCodeBody(200, [
            "user_id" => $account["user_id"]
        ]);
    }

    public function logoutUser(): void {

    }
}
