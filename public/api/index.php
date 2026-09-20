<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\ContactController;
use App\Database;

header('Content-Type: application/json');

/**
 * Respond and exit
 *
 * @param array{
 *     status: int,
 *     data: mixed
 * } $result
 */
function respond(array $result): never
{
    http_response_code($result['status']);
    echo json_encode($result['data']);
    exit;
}

/**
 * Get the decoded json data from php://input
 *
 * @return array<string, mixed>
 */
function getJsonData(): array
{
    $body = file_get_contents('php://input');
    if ($body === false) {
        respond(['status' => 400, 'data' => ['error' => 'Unable to read request body']]);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        respond(['status' => 400, 'data' => ['error' => 'Malformed JSON']]);
    }

    return $data;
}

// Start and validate session
session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId)) {
    respond(['status' => 401, 'data' => ['error' => 'Missing or expired session']]);
}

// Setup database and controllers
$pdo = Database::connect();
$controller = new ContactController($pdo);

// Get method and path
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$requestUri = $_SERVER['REQUEST_URI'] ?? null;
if (!is_string($requestUri)) {
    respond(['status' => 400, 'data' => ['error' => 'Invalid request URI']]);
}
$path = parse_url($requestUri, PHP_URL_PATH);
if (!is_string($path)) {
    respond(['status' => 400, 'data' => ['error' => 'Invalid request URI']]);
}

if ($path === '/api/contacts') {
    if ($method === 'GET') {
        $query = (string) ($_GET['query'] ?? '');
        $limit = (int) ($_GET['limit'] ?? 50);
        $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : null;

        respond($controller->search($userId, $query, $limit, $afterId));
    }

    if ($method === 'POST') {
        respond($controller->create($userId, getJsonData()));
    }

    respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
}

if (preg_match('#^/api/contacts/(\d+)$#', $path, $matches) === 1) {
    $contactId = (int) $matches[1];

    if ($method === 'PATCH') {
        respond($controller->update($userId, $contactId, getJsonData()));
    }

    if ($method === 'DELETE') {
        respond($controller->delete($userId, $contactId));
    }

    respond(['status' => 405, 'data' => ['error' => 'Unsupported HTTP method']]);
}

respond(['status' => 404, 'data' => ['error' => 'Not found']]);
