<?php

declare(strict_types=1);

namespace Tests;

use App\ContactController;
use App\UserRepository;
use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ContactControllerTest extends TestCase
{
    private PDO $pdo;
    private ContactController $controller;
    private UserRepository $users;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = Database::connect();
        $this->pdo->beginTransaction();

        $this->users = new UserRepository($this->pdo);
        $this->userId = $this->users->create(
            bin2hex(random_bytes(16)) . '@example.com',
            password_hash('test-password', PASSWORD_DEFAULT),
        );

        $this->controller = new ContactController($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /*
     * SEARCH TESTS
     */

    public function testSearchRejectsLimitBelowOne(): void
    {
        $result = $this->controller->search($this->userId, 'smith', 0);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid limit',
            $result['data']['error'],
        );
    }

    public function testSearchRejectsLimitAboveTwoHundred(): void
    {
        $result = $this->controller->search($this->userId, 'smith', 201);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid limit',
            $result['data']['error'],
        );
    }

    public function testSearchRejectsInvalidAfterId(): void
    {
        $result = $this->controller->search($this->userId, 'smith', 50, 0);

        self::assertSame(422, $result['status']);
        self::assertSame('Invalid after ID', $result['data']['error']);
    }

    public function testSearchAcceptsValidAfterId(): void
    {
        $contactId1 = $this->controller->create($this->userId, [
            'first_name' => 'C',
            'last_name' => 'C',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId2 = $this->controller->create($this->userId, [
            'first_name' => 'A',
            'last_name' => 'A',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId3 = $this->controller->create($this->userId, [
            'first_name' => 'B',
            'last_name' => 'B',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];

        $result = $this->controller->search($this->userId, '', 50, $contactId3);

        self::assertSame(200, $result['status']);
        self::assertCount(1, $result['data']['contacts']);
        self::assertSame($contactId1, $result['data']['contacts'][0]['contact_id']);
    }

    public function testSearchUsesDefaultLimit(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->controller->create($this->userId, [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone_number' => '1234567890',
            ]);
        }

        $result = $this->controller->search($this->userId, 'smith');

        self::assertSame(200, $result['status']);
        self::assertCount(50, $result['data']['contacts']);
    }

    public function testSearchKeepsOrderingOfSameNames(): void
    {
        $contactId1 = $this->controller->create($this->userId, [
            'first_name' => 'A',
            'last_name' => 'A',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId2 = $this->controller->create($this->userId, [
            'first_name' => 'A',
            'last_name' => 'A',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId3 = $this->controller->create($this->userId, [
            'first_name' => 'A',
            'last_name' => 'A',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];

        // Ensure the IDs are ordered. The database doesn't officially guarantee it
        $expectedOrder = [$contactId1, $contactId2, $contactId3];
        sort($expectedOrder);
        $contactId1 = $expectedOrder[0];
        $contactId2 = $expectedOrder[1];
        $contactId3 = $expectedOrder[2];

        // Assert consistent ordering between searches despite different after_id values
        $result = $this->controller->search($this->userId, '', 50, $contactId1);
        self::assertSame(200, $result['status']);
        self::assertCount(2, $result['data']['contacts']);
        self::assertSame($contactId2, $result['data']['contacts'][0]["contact_id"]);
        self::assertSame($contactId3, $result['data']['contacts'][1]["contact_id"]);

        $result = $this->controller->search($this->userId, '', 50, $contactId2);
        self::assertSame(200, $result['status']);
        self::assertCount(1, $result['data']['contacts']);
        self::assertSame($contactId3, $result['data']['contacts'][0]["contact_id"]);

        $result = $this->controller->search($this->userId, '', 50, $contactId3);
        self::assertSame(200, $result['status']);
        self::assertCount(0, $result['data']['contacts']);
    }

    public function testSearchReturnsContacts(): void
    {
        $contactId1 = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId2 = $this->controller->create($this->userId, [
            'first_name' => 'Smith',
            'last_name' => 'John',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $contactId3 = $this->controller->create($this->userId, [
            'first_name' => 'Fred',
            'last_name' => 'Rick',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];

        $result = $this->controller->search($this->userId, 'joh');
        self::assertSame(200, $result['status']);
        self::assertCount(2, $result['data']['contacts']);
        self::assertSame($contactId2, $result['data']['contacts'][0]["contact_id"]);
        self::assertSame($contactId1, $result['data']['contacts'][1]["contact_id"]);

        $result = $this->controller->search($this->userId, '');
        self::assertSame(200, $result['status']);
        self::assertCount(3, $result['data']['contacts']);
        self::assertSame($contactId2, $result['data']['contacts'][0]["contact_id"]);
        self::assertSame($contactId3, $result['data']['contacts'][1]["contact_id"]);
        self::assertSame($contactId1, $result['data']['contacts'][2]["contact_id"]);

        $result = $this->controller->search($this->userId, 'Z');
        self::assertSame(200, $result['status']);
        self::assertCount(0, $result['data']['contacts']);
    }

    public function testSearchReturnsEmptyArrayForNoMatch(): void
    {
        $result = $this->controller->search($this->userId, 'zzz-no-match');

        self::assertSame(200, $result['status']);
        self::assertSame([], $result['data']['contacts']);
    }

    /*
     * CREATE TESTS
     */

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidContactData')]
    public function testCreateRejectsInvalidFields(array $data): void
    {
        $result = $this->controller->create($this->userId, $data);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidContactData(): iterable
    {
        $valid = ['first_name' => 'John', 'last_name' => 'Smith', 'phone_number' => '1234567890'];
        foreach (array_keys($valid) as $field) {
            $missing = $valid;
            unset($missing[$field]);
            yield 'missing ' . $field => [$missing];
            yield 'empty ' . $field => [array_replace($valid, [$field => ''])];
            yield 'whitespace ' . $field => [array_replace($valid, [$field => " \t\n\u{00A0}\u{2003}\u{FEFF}"])];
        }
        foreach (['first_name' => 255, 'last_name' => 255, 'phone_number' => 20, 'company' => 255, 'email' => 255] as $field => $limit) {
            yield 'invalid type for ' . $field => [array_replace($valid, [$field => 123])];
            yield 'too long ' . $field => [array_replace($valid, [$field => str_repeat('A', $limit + 1)])];
            yield 'too many UTF-8 bytes ' . $field => [array_replace($valid, [$field => str_repeat('é', intdiv($limit, 2) + 1)])];
        }
        foreach (['not-an-email', 'ada@', 'ada@example', 'ada lovelace@example.com', "ada@example.com\nBcc: other@example.com", 'ada@例え.テスト'] as $email) {
            yield 'invalid email ' . $email => [$valid + ['email' => $email]];
        }
        yield 'invalid UTF-8' => [array_replace($valid, ['first_name' => "\xFF"])];
        yield 'extra field' => [$valid + ['extra' => 'invalid']];
    }

    /**
     * @param array<string, string|null> $expected
     * @param array<string, string|null> $input
     */
    #[DataProvider('normalisedContactData')]
    public function testCreateAndUpdateStoreNormalisedValues(array $expected, array $input): void
    {
        $created = $this->controller->create($this->userId, $input);
        self::assertSame(201, $created['status']);
        $contactId = $created['data']['contact_id'];
        self::assertSame(['contact_id' => $contactId] + $expected, $this->controller->search($this->userId, '')['data']['contacts'][0]);

        $this->controller->update($this->userId, $contactId, [
            'first_name' => 'Before', 'last_name' => 'Edit', 'phone_number' => '123',
            'company' => 'Before', 'email' => 'before@example.com',
        ]);
        $updated = $this->controller->update($this->userId, $contactId, $input);
        self::assertSame(200, $updated['status']);
        self::assertSame(['contact_id' => $contactId] + $expected, $this->controller->search($this->userId, '')['data']['contacts'][0]);
    }

    /** @return iterable<string, array{array<string, string|null>, array<string, string|null>}> */
    public static function normalisedContactData(): iterable
    {
        $blank = [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => null,
            'email' => null, 'phone_number' => '123-456-7890',
        ];
        foreach ([
            'Unicode names and formatted phone' => [
                'first_name' => 'Zoë', 'last_name' => '李', 'company' => 'Société',
                'email' => 'Zoe+work@Example.com', 'phone_number' => '+44 (20) 1234-5678',
            ],
            'whitespace optional fields' => $blank,
            'byte boundaries' => [
                'first_name' => str_repeat('é', 127) . 'a', 'last_name' => str_repeat('a', 255),
                'company' => str_repeat('é', 127) . 'a',
                'email' => str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61),
                'phone_number' => str_repeat('1', 20),
            ],
        ] as $name => $expected) {
            yield $name => [$expected, array_map(static fn(?string $value): string => "\u{00A0} \t" . ($value ?? '') . "\n\u{2003}\u{FEFF}", $expected)];
        }
        yield 'null optional fields' => [$blank, $blank];
        yield 'empty optional fields' => [$blank, array_replace($blank, ['company' => '', 'email' => ''])];
        yield 'omitted optional fields' => [$blank, array_diff_key($blank, ['company' => true, 'email' => true])];
    }

    public function testCreateSucceedsWithRequiredFieldsOnly(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(201, $result['status']);
        self::assertArrayHasKey('contact_id', $result['data']);
        self::assertGreaterThan(0, $result['data']['contact_id']);
    }

    public function testCreateSucceedsWithAllFields(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => 'Example Company',
            'email' => 'john@example.com',
        ]);

        self::assertSame(201, $result['status']);
        self::assertArrayHasKey('contact_id', $result['data']);
        self::assertGreaterThan(0, $result['data']['contact_id']);
    }

    /*
     * UPDATE TESTS
     */

    public function testUpdateRejectsInvalidContactId(): void
    {
        $result = $this->controller->update($this->userId, 0, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact ID', $result['data']['error']);
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidContactData')]
    public function testUpdateRejectsInvalidFields(array $data): void
    {
        $contactId = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];
        $result = $this->controller->update($this->userId, $contactId, $data);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);
    }

    public function testUpdateSucceeds(): void
    {
        $createResult = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(201, $createResult['status']);

        $contactId = $createResult['data']['contact_id'];

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone_number' => '9876543210',
            'company' => 'Updated Company',
            'email' => 'jane@example.com',
        ]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['data']['success']);
    }

    public function testUpdateReturnsNotFoundForNonexistentContact(): void
    {
        $result = $this->controller->update($this->userId, PHP_INT_MAX, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(404, $result['status']);
        self::assertSame(
            'Contact not found',
            $result['data']['error'],
        );
    }

    /*
     * DELETE TESTS
     */

    public function testDeleteRejectsInvalidContactId(): void
    {
        $result = $this->controller->delete($this->userId, 0);

        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact ID', $result['data']['error']);
    }

    public function testDeleteReturnsNotFoundForNonexistentContact(): void
    {
        $result = $this->controller->delete($this->userId, PHP_INT_MAX);

        self::assertSame(404, $result['status']);
        self::assertSame('Contact not found', $result['data']['error']);
    }

    public function testDeleteReturnsNotFoundForOtherUser(): void
    {
        $userIdOwner = $this->users->create(
            bin2hex(random_bytes(16)) . '@example.com',
            password_hash('test-password', PASSWORD_DEFAULT),
        );
        $result = $this->controller->create($userIdOwner, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        $result = $this->controller->delete($this->userId, $result['data']['contact_id']);

        self::assertSame(404, $result['status']);
        self::assertSame('Contact not found', $result['data']['error']);
    }

    public function testUpdateReturnsNotFoundForOtherUser(): void
    {
        $userIdOwner = $this->users->create(
            bin2hex(random_bytes(16)) . '@example.com',
            password_hash('test-password', PASSWORD_DEFAULT),
        );
        $result = $this->controller->create($userIdOwner, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        $result = $this->controller->update($this->userId, $result['data']['contact_id'], [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone_number' => '9876543210',
        ]);

        self::assertSame(404, $result['status']);
        self::assertSame('Contact not found', $result['data']['error']);
    }

    public function testDeleteSucceeds(): void
    {
        $createResult = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(201, $createResult['status']);

        $contactId = $createResult['data']['contact_id'];

        $result = $this->controller->delete($this->userId, $contactId);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['data']['success']);
    }

    public function testDeletedContactIsNoLongerReturnedBySearch(): void
    {
        $createResult = $this->controller->create($this->userId, [
            'first_name' => 'UniqueTestName',
            'last_name' => 'UniqueTestLastName',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(201, $createResult['status']);

        $contactId = $createResult['data']['contact_id'];

        $deleteResult = $this->controller->delete($this->userId, $contactId);

        self::assertSame(200, $deleteResult['status']);

        $searchResult = $this->controller->search(
            $this->userId,
            'UniqueTestName',
        );

        self::assertSame(200, $searchResult['status']);

        foreach ($searchResult['data']['contacts'] as $contact) {
            self::assertNotSame($contactId, $contact['contact_id']);
        }
    }
}
