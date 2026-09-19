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

    public function loginUser(): void {}

    public function logoutUser(): void {}
}
