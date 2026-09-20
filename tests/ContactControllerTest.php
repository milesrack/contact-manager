<?php

declare(strict_types=1);

namespace Tests;

use App\ContactController;
use App\UserRepository;
use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;

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

    public function testCreateRejectsMissingFirstName(): void
    {
        $result = $this->controller->create($this->userId, [
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsMissingLastName(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsMissingPhoneNumber(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsExtraFields(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'extra' => 'invalid',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsLongFields(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => str_repeat('A', 256),
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->create($this->userId, [
            'first_name' => "John",
            'last_name' => str_repeat('A', 256),
            'phone_number' => '1234567890',
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->create($this->userId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => str_repeat('A', 21),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->create($this->userId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => str_repeat('A', 256),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->create($this->userId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => str_repeat('A', 256),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);
    }

    public function testCreateRejectsInvalidFirstNameType(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 123,
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsInvalidLastNameType(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 123,
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsInvalidPhoneNumberType(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => 1234567890,
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsInvalidCompanyType(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => 123,
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsInvalidEmailType(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => 123,
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsEmptyFirstName(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => '',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsEmptyLastName(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => '',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testCreateRejectsEmptyPhoneNumber(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
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

    public function testCreateConvertsEmptyCompanyToNull(): void
    {
        $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => '',
        ]);
        $result = $this->controller->search($this->userId, 'John', 1);

        self::assertSame(200, $result['status']);
        self::assertNull($result['data']['contacts'][0]['company']);
    }

    public function testCreateConvertsEmptyEmailToNull(): void
    {
        $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => '',
        ]);
        $result = $this->controller->search($this->userId, 'John', 1);

        self::assertSame(200, $result['status']);
        self::assertNull($result['data']['contacts'][0]['email']);
    }

    public function testCreateAcceptsExplicitNullOptionalFields(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => null,
            'email' => null,
        ]);

        self::assertSame(201, $result['status']);

        $searchResult = $this->controller->search($this->userId, 'John', 1);

        self::assertSame(200, $searchResult['status']);
        self::assertSame('John', $searchResult['data']['contacts'][0]['first_name']);
        self::assertNull($searchResult['data']['contacts'][0]['company']);
        self::assertNull($searchResult['data']['contacts'][0]['email']);
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

    public function testUpdateRejectsMissingRequiredFields(): void
    {
        $result = $this->controller->update($this->userId, 1, [
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testUpdateRejectsExtraFields(): void
    {
        $result = $this->controller->update($this->userId, 1, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'extra' => 'invalid',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testUpdateRejectsLongFields(): void
    {
        $contactId = $this->controller->create($this->userId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ])['data']['contact_id'];

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => str_repeat('A', 256),
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => "John",
            'last_name' => str_repeat('A', 256),
            'phone_number' => '1234567890',
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => str_repeat('A', 21),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => str_repeat('A', 256),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);

        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => "John",
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => str_repeat('A', 256),
        ]);
        self::assertSame(422, $result['status']);
        self::assertSame('Invalid contact data', $result['data']['error']);
    }

    public function testUpdateRejectsInvalidFieldType(): void
    {
        $result = $this->controller->update($this->userId, 1, [
            'first_name' => 123,
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
    }

    public function testUpdateRejectsEmptyRequiredField(): void
    {
        $result = $this->controller->update($this->userId, 1, [
            'first_name' => '',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(422, $result['status']);
        self::assertSame(
            'Invalid contact data',
            $result['data']['error'],
        );
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

    public function testUpdateConvertsEmptyCompanyToNull(): void
    {
        $contactId = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => 'Google',
        ])['data']['contact_id'];
        $this->controller->update($this->userId, $contactId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => '',
        ]);
        $result = $this->controller->search($this->userId, 'John', 1);

        self::assertSame(200, $result['status']);
        self::assertNull($result['data']['contacts'][0]['company']);
    }

    public function testUpdateConvertsEmptyEmailToNull(): void
    {
        $contactId = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => 'Google',
            'email' => 'john@example.com',
        ])['data']['contact_id'];
        $this->controller->update($this->userId, $contactId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => '',
        ]);
        $result = $this->controller->search($this->userId, 'John', 1);

        self::assertSame(200, $result['status']);
        self::assertNull($result['data']['contacts'][0]['email']);
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

    public function testUpdateConvertsEmptyOptionalFieldsToNull(): void
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
            'company' => '',
            'email' => '',
        ]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['data']['success']);

        $result = $this->controller->search($this->userId, 'Jane', 1);

        self::assertNull($result['data']['contacts'][0]['company']);
        self::assertNull($result['data']['contacts'][0]['email']);
    }

    public function testUpdateAcceptsExplicitNullOptionalFields(): void
    {
        $createResult = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => 'Example Company',
            'email' => 'john@example.com',
        ]);

        self::assertSame(201, $createResult['status']);

        $contactId = $createResult['data']['contact_id'];
        $result = $this->controller->update($this->userId, $contactId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => null,
            'email' => null,
        ]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['data']['success']);

        $searchResult = $this->controller->search($this->userId, 'John', 1);

        self::assertNull($searchResult['data']['contacts'][0]['company']);
        self::assertNull($searchResult['data']['contacts'][0]['email']);
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
