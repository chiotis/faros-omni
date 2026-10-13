<?php

declare(strict_types=1);

namespace Omni\Models;

use PDO;

/** Counts attempts (failed sign-ins, connections) per address, to slow down guessing. */
final class Attempts
{
    public function __construct(private PDO $db)
    {
    }

    public function record(string $kind, string $key, ?int $now = null): void
    {
        $now ??= time();
        $this->db->prepare('INSERT INTO attempts (kind, key, at) VALUES (?, ?, ?)')->execute([$kind, $key, $now]);
        // Nothing is kept for longer than a day.
        $this->db->prepare('DELETE FROM attempts WHERE at < ?')->execute([$now - 86400]);
    }

    /** Seconds to wait before the next attempt, or 0 when fewer than $max were made in the last $window seconds. */
    public function retryAfter(string $kind, string $key, int $max, int $window, ?int $now = null): int
    {
        $now ??= time();
        $stmt = $this->db->prepare('SELECT at FROM attempts WHERE kind = ? AND key = ? AND at > ? ORDER BY at DESC LIMIT ' . max(1, $max));
        $stmt->execute([$kind, $key, $now - $window]);
        $times = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($times) < $max) {
            return 0;
        }
        return max(1, (int)end($times) + $window - $now);
    }

    public function clear(string $kind, string $key): void
    {
        $this->db->prepare('DELETE FROM attempts WHERE kind = ? AND key = ?')->execute([$kind, $key]);
    }
}
