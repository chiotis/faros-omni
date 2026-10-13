<?php

declare(strict_types=1);

namespace Omni\Models;

use PDO;

/** Settings of this Omni, as key and value (the owner's time zone). */
final class Settings
{
    public function __construct(private PDO $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
    }
}
