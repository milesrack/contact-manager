<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class ContactController
{
    private ContactRepository $contactRepository;

    public function __construct(PDO $pdo)
    {
        $this->contactRepository = new ContactRepository($pdo);
    }

    /**
     * Search the user's contacts. Supports pagination.
     *
     * @param int $userId
     * @param string $query
     * @param int $limit
     * @param int|null $afterId
     *
     * @return array{
     *     status: int,
     *     data: mixed
     * }
     */
    public function search(int $userId, string $query, int $limit = 50, ?int $afterId = null): array
    {
        // Restrict $limit and $afterId
        if ($limit < 1 || $limit > 200) {
            return ['status' => 422, 'data' => ['error' => 'Invalid limit']];
        }
        if ($afterId !== null && $afterId < 1) {
            return ['status' => 422, 'data' => ['error' => 'Invalid after ID']];
        }

        try {
            $contacts = $this->contactRepository->search($userId, $query, $limit, $afterId);
            return ['status' => 200, 'data' => ['contacts' => $contacts]];
        } catch (Throwable $e) {
            return ['status' => 500, 'data' => ['error' => 'Unexpected server error']];
        }
    }

    /**
     * Create a new contact for the user.
     *
     * @param int $userId
     * @param array<string, mixed> $data
     *
     * @return array{
     *     status: int,
     *     data: mixed
     * }
     */
    public function create(int $userId, array $data): array
    {
        $data = $this->validateContactData($data);
        if ($data === null) {
            return ['status' => 422, 'data' => ['error' => 'Invalid contact data']];
        }

        try {
            $contactId = $this->contactRepository->create($userId, $data);
            return ['status' => 201, 'data' => ['contact_id' => $contactId]];
        } catch (Throwable $e) {
            return ['status' => 500, 'data' => ['error' => 'Unexpected server error']];
        }
    }

    /**
     * Update an existing contact belonging to the user.
     *
     * @param int $userId
     * @param int $contactId
     * @param array<string, mixed> $data
     *
     * @return array{
     *     status: int,
     *     data: mixed
     * }
     */
    public function update(int $userId, int $contactId, array $data): array
    {
        if ($contactId < 1) {
            return ['status' => 422, 'data' => ['error' => 'Invalid contact ID']];
        }

        $data = $this->validateContactData($data);
        if ($data === null) {
            return ['status' => 422, 'data' => ['error' => 'Invalid contact data']];
        }

        try {
            if ($this->contactRepository->update($userId, $contactId, $data)) {
                return ['status' => 200, 'data' => ['success' => true]];
            } else {
                return ['status' => 404, 'data' => ['error' => 'Contact not found']];
            }
        } catch (Throwable $e) {
            return ['status' => 500, 'data' => ['error' => 'Unexpected server error']];
        }
    }

    /**
     * Delete a contact belonging to the user.
     *
     * @param int $userId
     * @param int $contactId
     *
     * @return array{
     *     status: int,
     *     data: mixed
     * }
     */
    public function delete(int $userId, int $contactId): array
    {
        if ($contactId < 1) {
            return ['status' => 422, 'data' => ['error' => 'Invalid contact ID']];
        }

        try {
            if ($this->contactRepository->delete($userId, $contactId)) {
                return ['status' => 200, 'data' => ['success' => true]];
            } else {
                return ['status' => 404, 'data' => ['error' => 'Contact not found']];
            }
        } catch (Throwable $e) {
            return ['status' => 500, 'data' => ['error' => 'Unexpected server error']];
        }
    }

    /**
     * Return normalised contact data, or null when invalid
     *
     * @param array<string, mixed> $data
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     phone_number: string,
     *     company: string|null,
     *     email: string|null
     * }
     */
    private function validateContactData(array $data): ?array
    {
        // Check required fields
        if (
            !isset($data['first_name'])
            || !isset($data['last_name'])
            || !isset($data['phone_number'])
        ) {
            return null;
        }

        // Supply defaults for optional fields
        $data['company'] = $data['company'] ?? null;
        $data['email'] = $data['email'] ?? null;
        // Reject if unnecessary fields were passed
        if (count($data) !== 5) {
            return null;
        }

        // Type checks
        if (
            !is_string($data['first_name'])
            || !is_string($data['last_name'])
            || !is_string($data['phone_number'])
            || !(is_string($data['company']) || is_null($data['company']))
            || !(is_string($data['email']) || is_null($data['email']))
        ) {
            return null;
        }

        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name']);
        $data['phone_number'] = trim($data['phone_number']);
        foreach (['company', 'email'] as $field) {
            if ($data[$field] !== null) {
                $value = trim($data[$field]);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        // Check that required fields are not empty strings
        if (
            $data['first_name'] === ''
            || $data['last_name'] === ''
            || $data['phone_number'] === ''
        ) {
            return null;
        }

        // Check field lengths
        if (
            strlen($data['first_name']) > 255
            || strlen($data['last_name']) > 255
            || strlen($data['phone_number']) > 20
            || ($data['company'] !== null && strlen($data['company']) > 255)
            || ($data['email'] !== null && strlen($data['email']) > 255)
        ) {
            return null;
        }

        return $data;
    }
}
