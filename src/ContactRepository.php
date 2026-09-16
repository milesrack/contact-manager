<?php

declare(strict_types=1);

namespace App;

use PDO;

final class ContactRepository
{
    public function __construct(private readonly PDO $pdo) {}


    /**
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     company: string|null,
     *     email: string|null,
     *     phone_number: string
     * } $data
     */
    public function create(int $userId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO contacts
                (user_id, first_name, last_name, company, email, phone_number)
            VALUES
                (:user_id, :first_name, :last_name, :company, :email, :phone_number)',
        );

        $company = $data['company'] === '' ? null : $data['company'];
        $email = $data['email'] === '' ? null : $data['email'];

        $statement->execute([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company' => $company,
            'email' => $email,
            'phone_number' => $data['phone_number'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }


    /**
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     company: string|null,
     *     email: string|null,
     *     phone_number: string
     * } $data
     */
    public function update(int $userId, int $contactId, array $data): bool
    {
        $company = $data['company'] === '' ? null : $data['company'];
        $email = $data['email'] === '' ? null : $data['email'];

        $statement = $this->pdo->prepare(
            'UPDATE contacts
             SET first_name = :first_name,
                 last_name = :last_name,
                 company = :company,
                 email = :email,
                 phone_number = :phone_number
             WHERE contact_id = :contact_id
                AND user_id = :user_id',
        );

        $statement->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company' => $company,
            'email' => $email,
            'phone_number' => $data['phone_number'],
            'contact_id' => $contactId,
            'user_id' => $userId,
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        }

        $checkStatement = $this->pdo->prepare(
            'SELECT contact_id
             FROM contacts
             WHERE contact_id = :contact_id
                AND user_id = :user_id',
        );

        $checkStatement->execute([
            'contact_id' => $contactId,
            'user_id' => $userId,
        ]);

        return $checkStatement->fetchColumn() !== false;
    }

    public function delete(int $userId, int $contactId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM contacts
             WHERE contact_id = :contact_id
                AND user_id = :user_id',
        );

        $statement->execute([
            'contact_id' => $contactId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(int $userId, string $query): array
    {
        $searchTerm = '%' . $query . '%';

        $statement = $this->pdo->prepare(
            'SELECT contact_id, first_name, last_name, company, email, phone_number
             FROM contacts
             WHERE user_id = :user_id
                AND (
                    first_name LIKE :first_name_query
                    OR last_name LIKE :last_name_query
                    OR company LIKE :company_query
                    OR email LIKE :email_query
                    OR phone_number LIKE :phone_query
                )
             ORDER BY contact_id ASC',
        );

        $statement->execute([
            'user_id' => $userId,
            'first_name_query' => $searchTerm,
            'last_name_query' => $searchTerm,
            'company_query' => $searchTerm,
            'email_query' => $searchTerm,
            'phone_query' => $searchTerm,
        ]);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values($results);
    }
}
