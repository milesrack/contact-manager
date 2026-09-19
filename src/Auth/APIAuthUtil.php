<?php

namespace App\Auth;

class APIAuthUtil
{
    public function __construct() {}

    public static function grabAndValidateCredentials(): ?array {
        $json = file_get_contents('php://input');

        try {
            $data = json_decode($json, true, flags:JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            APIAuthUtil::sendResponseCodeError(400, "Malformed JSON");
            return null;
        }

        // ensure parsed json contains correct fields
        if(!isset($data['email'], $data['password'])) {
            APIAuthUtil::sendResponseCodeError(422, "JSON requires 'email' and 'password' fields");
            return null;
        }

        $user = $data["email"];
        $pass = $data["password"];

        //validate email & password
        if(!is_string($user) || !filter_var($user, FILTER_VALIDATE_EMAIL)) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid email address.");
            return null;
        }
        if(!is_string($pass) || trim($pass) === "") {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid password.");
            return null;
        }

        return array("email" => $user, "password" => $pass);
    }

    public static function sendResponseCodeError(int $code, string $message, string $type = "error"): void {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode([
            $type => $code
        ]);
    }

    public static function sendResponseCodeBody(int $code, array $body): void {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode($body);
    }
}
