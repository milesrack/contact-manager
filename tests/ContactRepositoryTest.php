<?php

declare(strict_types=1);

namespace Tests;

use App\ContactRepository;
use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContactRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ContactRepository $contacts;

    protected function setUp(): void
    {
        $this->pdo = Database::connect();
        $this->pdo->beginTransaction();

        $this->contacts = new ContactRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function createTestUser(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash)
            VALUES (:email, :password_hash)',
        );

        $statement->execute([
            'email' => bin2hex(random_bytes(16)) . '@example.com',
            'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testCreatePersistsContactAndReturnsId(): void
    {
        $userId = $this->createTestUser();

        $contactId = $this->contacts->create($userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => 'Example Company',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        self::assertGreaterThan(0, $contactId);

        $statement = $this->pdo->prepare(
            'SELECT user_id, first_name, last_name, company, email, phone_number
             FROM contacts
             WHERE contact_id = :contact_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
        ]);

        $contact = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertSame($userId, (int) $contact['user_id']);
        self::assertSame('John', $contact['first_name']);
        self::assertSame('Smith', $contact['last_name']);
        self::assertSame('Example Company', $contact['company']);
        self::assertSame('john@example.com', $contact['email']);
        self::assertSame('4075551234', $contact['phone_number']);
    }

    public function testUpdateChangesOwnedContact(): void
    {
        $userId = $this->createTestUser();

        $contactId = $this->contacts->create($userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => 'Old Company',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        $updated = $this->contacts->update($userId, $contactId, [
            'first_name' => 'Jonathan',
            'last_name' => 'Smith',
            'company' => 'New Company',
            'email' => 'jonathan@example.com',
            'phone_number' => '4075559999',
        ]);

        self::assertTrue($updated);

        $statement = $this->pdo->prepare(
            'SELECT first_name, last_name, company, email, phone_number
             FROM contacts
             WHERE contact_id = :contact_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
        ]);

        $contact = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertSame('Jonathan', $contact['first_name']);
        self::assertSame('Smith', $contact['last_name']);
        self::assertSame('New Company', $contact['company']);
        self::assertSame('jonathan@example.com', $contact['email']);
        self::assertSame('4075559999', $contact['phone_number']);
    }

    public function testDeleteRemovesOwnedContact(): void
    {
        $userId = $this->createTestUser();

        $contactId = $this->contacts->create($userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => 'Example Company',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        $deleted = $this->contacts->delete($userId, $contactId);

        self::assertTrue($deleted);

        $statement = $this->pdo->prepare(
            'SELECT contact_id
             FROM contacts
             WHERE contact_id = :contact_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
        ]);

        self::assertFalse($statement->fetchColumn());
    }

    public function testSearchFindsPartialMatches(): void
    {
        $userId = $this->createTestUser();

        $this->contacts->create($userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => 'Example Company',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        $this->contacts->create($userId, [
            'first_name' => 'Alice',
            'last_name' => 'Jones',
            'company' => '',
            'email' => 'alice@example.com',
            'phone_number' => '3215559876',
        ]);

        $results = $this->contacts->search($userId, 'Jo');

        self::assertCount(2, $results);
        self::assertSame('Jones', $results[0]['last_name']);
        self::assertSame('John', $results[1]['first_name']);
    }

    public function testEmptySearchReturnUsersContacts(): void
    {
        $userId = $this->createTestUser();

        $this->contacts->create($userId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => '',
            'email' => '',
            'phone_number' => '4075551234',
        ]);

        $this->contacts->create($userId, [
            'first_name' => 'Alice',
            'last_name' => 'Brown',
            'company' => 'UCF',
            'email' => 'alice@example.com',
            'phone_number' => '3215559876',
        ]);

        $results = $this->contacts->search($userId, '');

        self::assertCount(2, $results);
    }

    public function testUpdateReturnsFalseForMissingContact(): void
    {
        $userId = $this->createTestUser();

        $updated = $this->contacts->update($userId, 999999999, [
            'first_name' => 'Nobody',
            'last_name' => 'Here',
            'company' => '',
            'email' => '',
            'phone_number' => '4075550000',
        ]);

        self::assertFalse($updated);
    }

    public function testDeleteReturnsFalseForMissingContact(): void
    {
        $userId = $this->createTestUser();
        $deleted = $this->contacts->delete($userId, 999999999);

        self::assertFalse($deleted);
    }

    public function testUserCannotModifyOtherUserContacts(): void
    {
        $ownerId = $this->createTestUser();
        $otherUserId = $this->createTestUser();

        $contactId = $this->contacts->create($ownerId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => 'Original Company',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        $updated = $this->contacts->update($otherUserId, $contactId, [
            'first_name' => 'Hacked',
            'last_name' => 'Contact',
            'company' => 'Wrong Company',
            'email' => 'wrong@example.com',
            'phone_number' => '0000000000',
        ]);

        self::assertFalse($updated);

        $deleted = $this->contacts->delete($otherUserId, $contactId);

        self::assertFalse($deleted);

        $statement = $this->pdo->prepare(
            'SELECT first_name
             FROM contacts
             WHERE contact_id = :contact_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
        ]);

        self::assertSame('John', $statement->fetchColumn());
    }

    public function testSearchOnlyReturnsCurrentUsersContacts(): void
    {
        $firstUserId = $this->createTestUser();
        $secondUserId = $this->createTestUser();

        $this->contacts->create($firstUserId, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'company' => '',
            'email' => 'john@example.com',
            'phone_number' => '4075551234',
        ]);

        $this->contacts->create($secondUserId, [
            'first_name' => 'Johnny',
            'last_name' => 'Jones',
            'company' => '',
            'email' => 'johnny@example.com',
            'phone_number' => '3215559876',
        ]);

        $results = $this->contacts->search($firstUserId, 'John');

        self::assertCount(1, $results);
        self::assertSame('John', $results[0]['first_name']);
    }

    public function testCreateStoresEmptyOptionalFieldsAsNull(): void
    {
        $userId = $this->createTestUser();

        $contactId = $this->contacts->create($userId, [
            'first_name' => 'Alice',
            'last_name' => 'Brown',
            'company' => '',
            'email' => '',
            'phone_number' => '4075554321',
        ]);

        $statement = $this->pdo->prepare(
            'SELECT company, email
             FROM contacts
             WHERE contact_id = :contact_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
        ]);

        $contact = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertNull($contact['company']);
        self::assertNull($contact['email']);
    }

    public function testSearchRespectsLimit(): void
    {
        $userId = $this->createTestUser();

        for ($i = 1; $i <= 5; ++$i) {
            $this->contacts->create($userId, [
                'first_name' => 'Test',
                'last_name' => 'Contact' . $i,
                'company' => '',
                'email' => '',
                'phone_number' => '4075550000' . $i,
            ]);
        }

        $results = $this->contacts->search($userId, '', 2);

        self::assertCount(2, $results);
    }

    public function testSearchSupportKeysetPagination(): void
    {
        $userId = $this->createTestUser();

        $firstId = $this->contacts->create($userId, [
            'first_name' => 'First',
            'last_name' => 'Contact',
            'company' => '',
            'email' => '',
            'phone_number' => '4075550001',
        ]);

        $secondId = $this->contacts->create($userId, [
            'first_name' => 'Second',
            'last_name' => 'Contact',
            'company' => '',
            'email' => '',
            'phone_number' => '4075550002',
        ]);

        $thirdId = $this->contacts->create($userId, [
            'first_name' => 'Third',
            'last_name' => 'Contact',
            'company' => '',
            'email' => '',
            'phone_number' => '4075550003',
        ]);

        $results = $this->contacts->search($userId, '', 50, $firstId);

        self::assertCount(2, $results);
        self::assertSame($secondId, (int) $results[0]['contact_id']);
        self::assertSame($thirdId, (int) $results[1]['contact_id']);
    }

    public function testSearchReturnsEmptyArrayForNoMatch(): void
    {
        $userId = $this->createTestUser();

        $results = $this->contacts->search($userId, 'does-not-exist');

        self::assertSame([], $results);
    }
}
