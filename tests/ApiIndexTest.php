<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use App\UserRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ApiIndexTest extends TestCase
{
    private static string $baseUrl;
    private static mixed $serverProcess;
    private static string $sessionDir;
    private static string $routerPath = '';
    private static string $serverStdout = '';
    private static string $serverStderr = '';

    private PDO $pdo;
    private UserRepository $users;
    private int $userId;
    private Client $client;
    /** @var list<int> */
    private array $extraUserIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$sessionDir = sys_get_temp_dir() . '/contact-manager-sessions-' . bin2hex(random_bytes(8));
        mkdir(self::$sessionDir, 0777, true);

        $port = self::findAvailablePort();
        self::$baseUrl = 'http://127.0.0.1:' . $port;
        self::startBuiltInServer($port);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
        }

        foreach ([self::$routerPath, self::$serverStdout, self::$serverStderr] as $path) {
            if ($path !== '' && file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir(self::$sessionDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(self::$sessionDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            rmdir(self::$sessionDir);
        }
    }

    protected function setUp(): void
    {
        $this->pdo = Database::connect();

        $this->users = new UserRepository($this->pdo);
        $this->userId = $this->users->create(
            bin2hex(random_bytes(16)) . '@example.com',
            password_hash('test-password', PASSWORD_DEFAULT),
        );

        $this->client = $this->createAuthenticatedClient($this->userId);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        foreach ([$this->userId, ...$this->extraUserIds] as $userId) {
            $this->pdo->prepare('DELETE FROM contacts WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            $this->pdo->prepare('DELETE FROM users WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        }
    }

    public function testUnauthenticatedFrontendRoutes(): void
    {
        $client = new Client(['base_uri' => self::$baseUrl, 'http_errors' => false, 'allow_redirects' => false]);
        $response = $client->get('/');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));

        foreach (['/login' => 'Log in', '/register' => 'Register'] as $path => $title) {
            $response = $client->get($path . '?next=test');
            self::assertSame(200, $response->getStatusCode());
            self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString($title, (string) $response->getBody());
            self::assertStringContainsString('/assets/css/app.css', (string) $response->getBody());
        }
    }

    public function testAuthenticatedFrontendRoutes(): void
    {
        $response = $this->client->get('/');
        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesRegularExpression('/<h1[^>]*>Contact Manager<\/h1>/', (string) $response->getBody());

        foreach (['/login', '/register'] as $path) {
            $response = $this->client->get($path);
            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/', $response->getHeaderLine('Location'));
        }
    }

    public function testInvalidSessionUserIdsDoNotAuthenticateFrontend(): void
    {
        foreach ([0, -1, '1', true, 1.5, null, []] as $userId) {
            $this->client->get('/__test__/session', ['query' => [
                'session_id' => 'frontend-test-' . bin2hex(random_bytes(16)),
                'user_id_json' => json_encode($userId, JSON_THROW_ON_ERROR),
            ]]);
            $response = $this->client->get('/');
            self::assertSame(302, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
            self::assertSame(200, $this->client->get('/login')->getStatusCode());
        }
    }

    public function testUnknownFrontendRoutesReturnHtml404(): void
    {
        foreach (['/missing', '/dashboard', '/contacts', '/apiary'] as $path) {
            $response = $this->client->get($path);
            self::assertSame(404, $response->getStatusCode());
            self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('Page not found', (string) $response->getBody());
        }
    }

    public function testUnsupportedFrontendMethodsReturn405(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) {
            foreach (['/', '/login', '/register'] as $path) {
                $response = $this->client->request($method, $path);
                self::assertSame(405, $response->getStatusCode());
                self::assertSame('GET', $response->getHeaderLine('Allow'));
            }
        }
    }

    public function testGetContactsWithoutSessionReturnsUnauthorized(): void
    {
        $client = new Client([
            'base_uri' => self::$baseUrl,
            'http_errors' => false,
            'timeout' => 10,
        ]);

        $response = $client->request('GET', '/api/contacts');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Missing or expired session'], $this->decodeJson($response));
    }

    public function testGetContactsReturnsEmptyArrayWhenNoRecordsMatch(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/contacts', [
            'query' => ['query' => 'zzz-no-match'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['contacts' => []], $this->decodeJson($response));
    }

    public function testGetContactsRejectsLimitBelowOne(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/contacts', [
            'query' => ['limit' => 0],
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid limit'], $this->decodeJson($response));
    }

    public function testGetContactsRejectsInvalidAfterId(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/contacts', [
            'query' => ['after_id' => 0],
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid after ID'], $this->decodeJson($response));
    }

    public function testPostContactsRejectsMalformedJson(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            RequestOptions::BODY => '{not valid json}',
        ]);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Malformed JSON'], $this->decodeJson($response));
    }

    public function testPostContactsRejectsMissingRequiredFields(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'John',
                'last_name' => 'Smith',
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid contact data'], $this->decodeJson($response));
    }

    public function testPostContactsCreatesContact(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'phone_number' => '1234567890',
                'company' => 'Analytical Engine',
                'email' => 'ada@example.com',
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('contact_id', $payload);
        self::assertGreaterThan(0, $payload['contact_id']);
    }

    public function testPatchContactUpdatesExistingRecord(): void
    {
        $createResponse = $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'phone_number' => '5551234567',
            ],
        ]);
        $contactId = $this->decodeJson($createResponse)['contact_id'];

        $response = $this->authenticatedRequest('PATCH', '/api/contacts/' . $contactId, [
            RequestOptions::JSON => [
                'first_name' => 'Grace',
                'last_name' => 'Murray',
                'phone_number' => '5559876543',
                'company' => 'Naval Research',
                'email' => 'grace@example.com',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->decodeJson($response));
    }

    public function testDeleteContactRemovesRecord(): void
    {
        $createResponse = $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'Alan',
                'last_name' => 'Turing',
                'phone_number' => '123',
            ],
        ]);
        $contactId = $this->decodeJson($createResponse)['contact_id'];

        $response = $this->authenticatedRequest('DELETE', '/api/contacts/' . $contactId);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->decodeJson($response));
    }

    public function testPatchContactReturnsNotFoundForMissingContact(): void
    {
        $response = $this->authenticatedRequest('PATCH', '/api/contacts/999999', [
            RequestOptions::JSON => [
                'first_name' => 'X',
                'last_name' => 'Y',
                'phone_number' => '111',
            ],
        ]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Contact not found'], $this->decodeJson($response));
    }

    public function testPatchContactReturnsNotFoundForOtherUsersContact(): void
    {
        [, $otherClient] = $this->createOtherUserClient();

        $createResponse = $otherClient->request('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone_number' => '1234567890',
            ],
        ]);
        $contactId = $this->decodeJson($createResponse)['contact_id'];

        $response = $this->authenticatedRequest('PATCH', '/api/contacts/' . $contactId, [
            RequestOptions::JSON => [
                'first_name' => 'X',
                'last_name' => 'Y',
                'phone_number' => '111',
            ],
        ]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Contact not found'], $this->decodeJson($response));
    }

    public function testDeleteContactReturnsNotFoundForMissingContact(): void
    {
        $response = $this->authenticatedRequest('DELETE', '/api/contacts/999999');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Contact not found'], $this->decodeJson($response));
    }

    public function testDeleteContactReturnsNotFoundForOtherUsersContact(): void
    {
        [, $otherClient] = $this->createOtherUserClient();

        $createResponse = $otherClient->request('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'phone_number' => '1234567890',
            ],
        ]);
        $contactId = $this->decodeJson($createResponse)['contact_id'];

        $response = $this->authenticatedRequest('DELETE', '/api/contacts/' . $contactId);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Contact not found'], $this->decodeJson($response));
    }

    public function testGetContactsReturnsMatchingContact(): void
    {
        $this->authenticatedRequest('POST', '/api/contacts', [
            RequestOptions::JSON => [
                'first_name' => 'Marie',
                'last_name' => 'Curie',
                'phone_number' => '5551112222',
            ],
        ]);

        $response = $this->authenticatedRequest('GET', '/api/contacts', [
            'query' => ['query' => 'curie'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['contacts']);
        self::assertSame('Marie', $payload['contacts'][0]['first_name']);
        self::assertSame('Curie', $payload['contacts'][0]['last_name']);
    }

    public function testUnsupportedMethodOnContactsRouteReturns405(): void
    {
        $response = $this->authenticatedRequest('PUT', '/api/contacts');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['error' => 'Unsupported HTTP method'], $this->decodeJson($response));
    }

    public function testUnsupportedMethodOnContactRouteReturns405(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/contacts/1');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['error' => 'Unsupported HTTP method'], $this->decodeJson($response));
    }

    public function testUnknownRouteReturns404(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/missing');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not found'], $this->decodeJson($response));
    }

    public function testAuthenticationAndContactLifecycle(): void
    {
        $cookies = new CookieJar();
        $client = new Client(['base_uri' => self::$baseUrl, 'http_errors' => false, 'cookies' => $cookies]);
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $credentials = ['email' => $email, 'password' => 'test-password'];
        $response = $client->post('/api/auth/register', ['json' => $credentials]);
        self::assertSame(201, $response->getStatusCode());
        $userId = $this->decodeJson($response)['user_id'];
        self::assertIsInt($userId);
        $this->extraUserIds[] = $userId;
        $account = $this->users->findByEmail($email);
        self::assertNotNull($account);
        self::assertTrue(password_verify($credentials['password'], $account['password_hash']));
        self::assertSame(401, $client->get('/api/contacts')->getStatusCode());
        $oldSession = $this->sessionId($cookies);
        self::assertNotSame('', $oldSession);

        $response = $client->post('/api/auth/login', ['json' => $credentials]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['user_id' => $userId], $this->decodeJson($response));
        $loginSession = $this->sessionId($cookies);
        self::assertNotSame('', $loginSession);
        self::assertNotSame($oldSession, $loginSession);
        $oldClient = new Client(['base_uri' => self::$baseUrl, 'http_errors' => false]);
        self::assertSame(401, $oldClient->get('/api/contacts', ['headers' => ['Cookie' => 'PHPSESSID=' . $oldSession]])->getStatusCode());

        $data = ['first_name' => ' Ada ', 'last_name' => ' Lovelace ', 'phone_number' => ' 123 ', 'company' => '   ', 'email' => '   '];
        $response = $client->post('/api/contacts', ['json' => $data]);
        self::assertSame(201, $response->getStatusCode());
        $contactId = $this->decodeJson($response)['contact_id'];
        $response = $client->get('/api/contacts?query=love');
        self::assertSame(200, $response->getStatusCode());
        $contacts = $this->decodeJson($response)['contacts'];
        self::assertCount(1, $contacts);
        self::assertSame('Ada', $contacts[0]['first_name']);
        self::assertNull($contacts[0]['company']);
        self::assertNull($contacts[0]['email']);
        $url = '/api/contacts/' . $contactId;

        self::assertSame([], $this->decodeJson($this->client->get('/api/contacts'))['contacts']);
        self::assertSame(404, $this->client->patch($url, ['json' => $data])->getStatusCode());
        self::assertSame(404, $this->client->delete($url)->getStatusCode());
        self::assertCount(1, $this->decodeJson($client->get('/api/contacts'))['contacts']);
        $data['last_name'] = 'Byron';
        self::assertSame(200, $client->patch($url, ['json' => $data])->getStatusCode());
        self::assertSame('Byron', $this->decodeJson($client->get('/api/contacts'))['contacts'][0]['last_name']);
        self::assertSame(200, $client->delete($url)->getStatusCode());
        self::assertSame([], $this->decodeJson($client->get('/api/contacts'))['contacts']);

        $response = $client->post('/api/auth/logout');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->decodeJson($response));
        self::assertSame(401, $client->get('/api/contacts')->getStatusCode());
        self::assertSame(401, $oldClient->get('/api/contacts', ['headers' => ['Cookie' => 'PHPSESSID=' . $loginSession]])->getStatusCode());
    }

    public function testDuplicateRegistrationReturnsJsonValidationError(): void
    {
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $this->extraUserIds[] = $this->users->create($email, password_hash('test-password', PASSWORD_DEFAULT));
        $response = $this->client->post('/api/auth/register', ['json' => ['email' => $email, 'password' => 'test-password']]);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Email address is already registered.'], $this->decodeJson($response));
    }

    public function testFailedLoginDoesNotAuthenticate(): void
    {
        $client = new Client(['base_uri' => self::$baseUrl, 'http_errors' => false, 'cookies' => new CookieJar()]);
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $this->extraUserIds[] = $this->users->create($email, password_hash('correct-password', PASSWORD_DEFAULT));
        foreach ([$email, 'missing-' . $email] as $loginEmail) {
            $response = $client->post('/api/auth/login', ['json' => ['email' => $loginEmail, 'password' => 'wrong-password']]);
            self::assertSame(401, $response->getStatusCode());
            self::assertSame(['error' => 'Invalid Credentials'], $this->decodeJson($response));
            self::assertSame(401, $client->get('/api/contacts')->getStatusCode());
        }
    }

    public function testAuthenticationRejectsInvalidRequests(): void
    {
        foreach (['register', 'login', 'logout'] as $route) {
            $response = $this->client->get('/api/auth/' . $route);
            self::assertSame(405, $response->getStatusCode());
            self::assertSame('POST', $response->getHeaderLine('Allow'));
        }
        foreach (['register', 'login'] as $route) {
            $response = $this->client->post('/api/auth/' . $route, ['body' => '{']);
            self::assertSame(400, $response->getStatusCode());
            self::assertArrayHasKey('error', $this->decodeJson($response));
            $response = $this->client->post('/api/auth/' . $route, ['json' => ['email' => []]]);
            self::assertSame(422, $response->getStatusCode());
        }
        foreach ([['email' => 'invalid', 'password' => 'test'], ['email' => 'valid@example.com', 'password' => '  '], ['email' => 'valid@example.com', 'password' => str_repeat('x', 73)], ['email' => 'valid@example.com', 'password' => "test\0password"]] as $credentials) {
            $response = $this->client->post('/api/auth/register', ['json' => $credentials]);
            self::assertSame(422, $response->getStatusCode());
            self::assertArrayHasKey('error', $this->decodeJson($response));
        }
    }

    public function testDatabaseFailureReturnsJson(): void
    {
        $envPath = self::$sessionDir . '/app/.env';
        $dotenv = file_get_contents($envPath);
        self::assertIsString($dotenv);
        try {
            file_put_contents($envPath, $dotenv . "\nDB_PORT=0\n");
            $response = $this->client->post('/api/auth/login', ['json' => ['email' => 'test@example.com', 'password' => 'test']]);
            self::assertSame(500, $response->getStatusCode());
            self::assertSame(['error' => 'Unexpected server error'], $this->decodeJson($response));
        } finally {
            file_put_contents($envPath, $dotenv);
        }
    }

    public function testSearchRejectsMalformedParameters(): void
    {
        foreach (['query[]=smith', 'limit=1junk', 'limit[]=1', 'limit=1.5', 'after_id=2junk', 'after_id[]=2'] as $query) {
            $response = $this->client->get('/api/contacts?' . $query);
            self::assertSame(422, $response->getStatusCode(), $query);
            self::assertArrayHasKey('error', $this->decodeJson($response));
        }
    }

    public function testContactWritesRejectWhitespaceRequiredFields(): void
    {
        $data = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone_number' => '123'];
        $response = $this->client->post('/api/contacts', ['json' => $data]);
        $contactId = $this->decodeJson($response)['contact_id'];
        foreach (array_keys($data) as $field) {
            $invalid = array_replace($data, [$field => '   ']);
            self::assertSame(422, $this->client->post('/api/contacts', ['json' => $invalid])->getStatusCode());
            self::assertSame(422, $this->client->patch('/api/contacts/' . $contactId, ['json' => $invalid])->getStatusCode());
        }
        self::assertSame('Ada', $this->decodeJson($this->client->get('/api/contacts'))['contacts'][0]['first_name']);
    }

    private function sessionId(CookieJar $cookies): string
    {
        $cookie = $cookies->getCookieByName('PHPSESSID');
        self::assertNotNull($cookie);
        $value = $cookie->getValue();
        self::assertIsString($value);
        return $value;
    }

    private static function findAvailablePort(): int
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::fail('Unable to allocate a free port for the API test server: ' . $errstr);
        }

        $address = stream_socket_get_name($server, false);
        fclose($server);

        if ($address === false) {
            self::fail('Unable to determine port from the test server socket.');
        }

        $parts = explode(':', $address);

        return (int) end($parts);
    }

    private static function startBuiltInServer(int $port): void
    {
        self::$routerPath = sys_get_temp_dir() . '/contact-manager-router-' . bin2hex(random_bytes(8)) . '.php';
        self::$serverStdout = sys_get_temp_dir() . '/contact-manager-server-' . bin2hex(random_bytes(8)) . '.stdout.log';
        self::$serverStderr = sys_get_temp_dir() . '/contact-manager-server-' . bin2hex(random_bytes(8)) . '.stderr.log';
        // Exercise the API with .env configuration, as deployed, without inherited DB variables.
        $projectRoot = self::$sessionDir . '/app';
        mkdir($projectRoot . '/public/api', 0700, true);
        mkdir($projectRoot . '/config', 0700, true);
        foreach (['public/api/index.php', 'public/index.php', 'config/bootstrap.php', 'config/app.php', 'config/twig.php'] as $file) {
            copy(dirname(__DIR__) . '/' . $file, $projectRoot . '/' . $file);
        }
        symlink(dirname(__DIR__) . '/templates', $projectRoot . '/templates');
        symlink(dirname(__DIR__) . '/vendor', $projectRoot . '/vendor');
        $serverEnv = getenv();
        $dotenv = '';
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'APP_ENV', 'APP_URL'] as $key) {
            $dotenv .= $key . '=' . getenv($key) . "\n";
            unset($serverEnv[$key]);
        }
        file_put_contents($projectRoot . '/.env', $dotenv);

        $routerCode = <<<'PHP'
<?php

$projectRoot = %s;
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

if ($path === '/__test__/session') {
    $sessionId = $_GET['session_id'] ?? '';
    if (!is_string($sessionId) || $sessionId === '') {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Missing session id']);
        return;
    }

    session_name('PHPSESSID');
    session_save_path(%s);
    session_id($sessionId);
    session_start();
    $_SESSION['user_id'] = isset($_GET['user_id_json'])
        ? json_decode($_GET['user_id_json'], true, flags: JSON_THROW_ON_ERROR)
        : (int) ($_GET['user_id'] ?? 0);
    session_write_close();
    header('Set-Cookie: PHPSESSID=' . $sessionId . '; path=/; HttpOnly');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    return;
}

if (is_file($projectRoot . '/public' . $path)) {
    return false;
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require $projectRoot . '/public/api/index.php';
    return;
}

require $projectRoot . '/public/index.php';
PHP;

        $routerCode = sprintf($routerCode, var_export($projectRoot, true), var_export(self::$sessionDir, true));
        file_put_contents(self::$routerPath, $routerCode);

        $process = proc_open(
            ['php', '-d', 'session.save_path=' . self::$sessionDir, '-S', '127.0.0.1:' . $port, '-t', $projectRoot . '/public', self::$routerPath],
            [
                1 => ['file', self::$serverStdout, 'w'],
                2 => ['file', self::$serverStderr, 'w'],
            ],
            $pipes,
            null,
            $serverEnv,
            ['bypass_shell' => true],
        );

        self::$serverProcess = $process;

        $client = new Client([
            'base_uri' => self::$baseUrl,
            'http_errors' => false,
            'timeout' => 2,
        ]);

        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            try {
                $response = $client->request('GET', '/api/contacts');
                if ($response->getStatusCode() === 401) {
                    return;
                }
            } catch (\Throwable $exception) {
                usleep(100000);
                continue;
            }

            usleep(100000);
        }

        self::fail('PHP built-in server did not start in time.');
    }

    /**
     * Create a Guzzle client with its own cookie jar, authenticated as the given user.
     */
    private function createAuthenticatedClient(int $userId): Client
    {
        $client = new Client([
            'base_uri' => self::$baseUrl,
            'http_errors' => false,
            'timeout' => 10,
            'cookies' => new CookieJar(),
            'allow_redirects' => false,
        ]);

        $response = $client->request('GET', '/__test__/session', [
            'query' => [
                'user_id' => $userId,
                'session_id' => 'api-test-' . bin2hex(random_bytes(16)),
            ],
            'http_errors' => false,
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['ok' => true], $this->decodeJson($response));

        return $client;
    }

    /**
     * Create a second user + authenticated client, tracked for teardown.
     *
     * @return array{0: int, 1: Client}
     */
    private function createOtherUserClient(): array
    {
        $userId = $this->users->create(
            bin2hex(random_bytes(16)) . '@example.com',
            password_hash('test-password', PASSWORD_DEFAULT),
        );
        $this->extraUserIds[] = $userId;

        return [$userId, $this->createAuthenticatedClient($userId)];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authenticatedRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
