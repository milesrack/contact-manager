<?php

declare(strict_types=1);

namespace Tests;

use App\ContactController;
use App\Database;
use App\UserRepository;
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
        $email = bin2hex(random_bytes(16)) . '@example.com';
        $passwordHash = password_hash('test-password', PASSWORD_DEFAULT);
        $this->userId = $this->users->create($email, $passwordHash);

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

    public function testSearchRejectsInvalidUserId(): void
    {
        $result = $this->controller->search(0, 'smith');

        self::assertSame(401, $result['status']);
        self::assertSame('Invalid user ID', $result['data']['error']);
    }

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
        $result = $this->controller->search($this->userId, 'smith', 50, 1);

        self::assertSame(200, $result['status']);
        self::assertArrayHasKey('contacts', $result['data']);
        self::assertIsArray($result['data']['contacts']);
    }

    public function testSearchUsesDefaultLimit(): void
    {
        $result = $this->controller->search($this->userId, 'smith');

        self::assertSame(200, $result['status']);
        self::assertArrayHasKey('contacts', $result['data']);
    }

    public function testSearchReturnsContacts(): void
    {
        $result = $this->controller->search($this->userId, '');

        self::assertSame(200, $result['status']);
        self::assertIsArray($result['data']['contacts']);
    }

    /*
     * CREATE TESTS
     */

    public function testCreateRejectsInvalidUserId(): void
    {
        $result = $this->controller->create(0, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(401, $result['status']);
        self::assertSame('Invalid user ID', $result['data']['error']);
    }

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
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'company' => '',
        ]);

        self::assertSame(201, $result['status']);
        self::assertArrayHasKey('contact_id', $result['data']);
    }

    public function testCreateConvertsEmptyEmailToNull(): void
    {
        $result = $this->controller->create($this->userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
            'email' => '',
        ]);

        self::assertSame(201, $result['status']);
        self::assertArrayHasKey('contact_id', $result['data']);
    }

    /*
     * UPDATE TESTS
     */

    public function testUpdateRejectsInvalidUserId(): void
    {
        $result = $this->controller->update(0, 1, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '1234567890',
        ]);

        self::assertSame(401, $result['status']);
        self::assertSame('Invalid user ID', $result['data']['error']);
    }

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
    }

    /*
     * DELETE TESTS
     */

    public function testDeleteRejectsInvalidUserId(): void
    {
        $result = $this->controller->delete(0, 1);

        self::assertSame(401, $result['status']);
        self::assertSame('Invalid user ID', $result['data']['error']);
    }

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
        self::assertSame(
            'Contact not found',
            $result['data']['error'],
        );
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
