<?php

declare(strict_types=1);

use App\AuthController;
use App\ContactController;
use App\Database;
use App\UserRepository;

ini_set('display_errors', '0');
header('Content-Type: application/json');

/**
 * @param array{status: int, data: mixed} $result
 */
function respond(array $result): never
{
    $json = json_encode($result['data'], JSON_THROW_ON_ERROR);
    http_response_code($result['status']);
    echo $json;
    exit;
}

/**
 * @return array<string, mixed>
 */
function getJsonData(): array
{
    $body = file_get_contents('php://input');
    if ($body === false) {
        throw new RuntimeException('Unable to read request body');
    }
    try {
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        respond(['status' => 400, 'data' => ['error' => 'Malformed JSON']]);
    }
    if (!is_array($data)) {
        respond(['status' => 422, 'data' => ['error' => 'Expected a JSON object']]);
    }
    return $data;
}

/**
 * @return array{email: string, password: string}
 */
function getCredentials(): array
{
    $data = getJsonData();
    if (!isset($data['email'], $data['password'])) {
        respond(['status' => 422, 'data' => ['error' => "JSON requires 'email' and 'password' fields"]]);
    }
    if (!is_string($data['email'])) {
        respond(['status' => 422, 'data' => ['error' => 'Please enter a valid email address.']]);
    }
    if (!is_string($data['password'])) {
        respond(['status' => 422, 'data' => ['error' => 'Please enter a valid password.']]);
    }
    return ['email' => $data['email'], 'password' => $data['password']];
}

try {
    require_once __DIR__ . '/../../config/bootstrap.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!session_start()) {
        throw new RuntimeException('Unable to start session');
    }

    if ($path === '/api/auth/register') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
        }
        $credentials = getCredentials();
        $authController = new AuthController(new UserRepository(Database::connect()));
        $result = $authController->registerUser($credentials['email'], $credentials['password']);
        if ($result['status'] === 201) {
            $_SESSION['registration_complete'] = true;
        }
        respond($result);
    } elseif ($path === '/api/auth/login') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
        }
        $credentials = getCredentials();
        $authController = new AuthController(new UserRepository(Database::connect()));
        respond($authController->loginUser($credentials['email'], $credentials['password']));
    } elseif ($path === '/api/auth/logout') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
        }
        respond(AuthController::logoutUser());
    } elseif ($path === '/api/contacts') {
        if ($method !== 'GET' && $method !== 'POST') {
            header('Allow: GET, POST');
            respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            respond(['status' => 401, 'data' => ['error' => 'Missing or expired session']]);
        }
        $controller = new ContactController(Database::connect());

        if ($method === 'GET') {
            $query = $_GET['query'] ?? '';
            $limit = filter_var($_GET['limit'] ?? '50', FILTER_VALIDATE_INT);
            $afterId = isset($_GET['after_id']) ? filter_var($_GET['after_id'], FILTER_VALIDATE_INT) : null;
            if (!is_string($query) || $limit === false || $afterId === false) {
                respond(['status' => 422, 'data' => ['error' => 'Invalid search parameters']]);
            }
            respond($controller->search($userId, $query, $limit, $afterId));
        } elseif ($method === 'POST') {
            respond($controller->create($userId, getJsonData()));
        }
    } elseif (is_string($path) && preg_match('#^/api/contacts/(\d+)$#', $path, $matches) === 1) {
        if ($method !== 'PATCH' && $method !== 'DELETE') {
            header('Allow: PATCH, DELETE');
            respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            respond(['status' => 401, 'data' => ['error' => 'Missing or expired session']]);
        }
        $controller = new ContactController(Database::connect());

        $contactId = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($contactId === false) {
            respond(['status' => 422, 'data' => ['error' => 'Invalid contact ID']]);
        }
        if ($method === 'PATCH') {
            respond($controller->update($userId, $contactId, getJsonData()));
        } elseif ($method === 'DELETE') {
            respond($controller->delete($userId, $contactId));
        }
    } else {
        respond(['status' => 404, 'data' => ['error' => 'Not found']]);
    }
} catch (Throwable $e) {
    error_log((string) $e);
    respond(['status' => 500, 'data' => ['error' => 'Unexpected server error']]);
}
