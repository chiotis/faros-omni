<?php

declare(strict_types=1);

namespace Omni\Core;

/**
 * Connection codes: 16 characters from an alphabet without look-alikes, shown as XXXX-XXXX-XXXX-XXXX (about 79 bits).
 * Only their hash is kept.
 */
final class ConnectCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const LENGTH = 16;
    public const LIFETIME = 86400;

    public static function generate(): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }
        return implode('-', str_split($chars, 4));
    }

    /** The code as typed, in capitals and without dashes or spaces; '' when it cannot be a code. */
    public static function normalize(string $input): string
    {
        $chars = preg_replace('/[\s-]+/', '', strtoupper($input)) ?? '';
        if (strlen($chars) !== self::LENGTH || strspn($chars, self::ALPHABET) !== self::LENGTH) {
            return '';
        }
        return $chars;
    }

    public static function hash(string $input): string
    {
        return hash('sha256', self::normalize($input));
    }
}
