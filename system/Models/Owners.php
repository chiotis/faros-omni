<?php

declare(strict_types=1);

namespace Omni\Models;

use PDO;

/** The owner of this Omni: the only account there is. */
final class Owners
{
    public function __construct(private PDO $db)
    {
    }

    public function exists(): bool
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM owners')->fetchColumn() > 0;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM owners WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM owners WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Makes the owner, only while there is none (in one transaction, so two setups at once cannot both succeed).
     * Returns the new id, or 0 when an owner already exists.
     */
    public function createFirst(string $name, string $email, string $password): int
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            if ($this->exists()) {
                $this->db->exec('ROLLBACK');
                return 0;
            }
            $stmt = $this->db->prepare('INSERT INTO owners (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), gmdate('c')]);
            $id = (int)$this->db->lastInsertId();
            $this->db->exec('COMMIT');
            return $id;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE owners SET last_login_at = ? WHERE id = ?')->execute([gmdate('c'), $id]);
    }

    public function rehashIfNeeded(int $id, string $hash, string $password): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE owners SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        }
    }
}
