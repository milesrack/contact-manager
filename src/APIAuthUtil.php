<?php

declare(strict_types=1);

namespace App;

class APIAuthUtil
{
    public function __construct() {}

    /**
     * @return array{email: string, password: string}|null
     */
    public static function grabAndValidateCredentials(): ?array
    {
        $json = file_get_contents('php://input');

        if ($json === false) {
            APIAuthUtil::sendResponseCodeError(500, "Unable to read request body");
            return null;
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            APIAuthUtil::sendResponseCodeError(400, "Malformed JSON");
            return null;
        }

        // ensure parsed json contains correct fields
        if (!is_array($data) || !isset($data['email'], $data['password'])) {
            APIAuthUtil::sendResponseCodeError(422, "JSON requires 'email' and 'password' fields");
            return null;
        }

        $email = $data["email"];
        $pass = $data["password"];

        //validate email & password
        if (!is_string($email)) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid email address.");
            return null;
        }
        if (!is_string($pass) || trim($pass) === "" || strlen($pass) > 72 || str_contains($pass, "\0")) {
            APIAuthUtil::sendResponseCodeError(422, "Please enter a valid password.");
            return null;
        }

        return ["email" => $email, "password" => $pass];
    }

    public static function sendResponseCodeError(int $code, string $message, string $type = "error"): void
    {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode([
            $type => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function sendResponseCodeBody(int $code, array $body): void
    {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode($body);
    }
}
