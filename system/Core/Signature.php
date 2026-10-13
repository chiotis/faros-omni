<?php

declare(strict_types=1);

namespace Omni\Core;

/**
 * The signature of a request from a site (protocol v1): HMAC-SHA256, keyed with the site's secret as it was given at
 * connect, of the method, the path, the timestamp, the nonce and the SHA-256 of the body, one per line.
 */
final class Signature
{
    /** How far a request's clock may be from ours, in seconds. */
    public const MAX_SKEW = 300;

    public static function stringToSign(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);
    }

    /** The value of the X-Omni-Signature header. */
    public static function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return 'v1=' . hash_hmac('sha256', self::stringToSign($method, $path, $timestamp, $nonce, $body), $secret);
    }

    public static function matches(string $secret, string $header, string $method, string $path, string $timestamp, string $nonce, string $body): bool
    {
        return hash_equals(self::sign($secret, $method, $path, $timestamp, $nonce, $body), trim($header));
    }

    /** Whether the timestamp is a whole number of seconds within MAX_SKEW of now. */
    public static function fresh(string $timestamp, int $now): bool
    {
        return preg_match('/^\d{1,12}$/', $timestamp) === 1 && abs($now - (int)$timestamp) <= self::MAX_SKEW;
    }

    /** A nonce is 16 random bytes in hex. */
    public static function validNonce(string $nonce): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $nonce) === 1;
    }
}
