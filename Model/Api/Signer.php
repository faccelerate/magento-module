<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Api;

/**
 * Request signing for the signed endpoints (/x/magento/link, /x/magento/disconnect), see CONTRACT.md:
 * lowercase hex of HMAC-SHA256(key = consumer secret, data = METHOD "\n" PATH "\n" TIMESTAMP).
 */
class Signer
{
    public const HEADER_CONSUMER = 'X-Faccelerate-Consumer';
    public const HEADER_TIMESTAMP = 'X-Faccelerate-Timestamp';
    public const HEADER_SIGNATURE = 'X-Faccelerate-Signature';

    /**
     * @param string $path Request path without host and query, e.g. /x/magento/link
     */
    public function sign(string $consumerSecret, string $method, string $path, int $timestamp): string
    {
        return hash_hmac('sha256', strtoupper($method) . "\n" . $path . "\n" . $timestamp, $consumerSecret);
    }

    /**
     * @return array<string, string> The three signing headers
     */
    public function headers(
        string $consumerKey,
        string $consumerSecret,
        string $method,
        string $path,
        int $timestamp
    ): array {
        return [
            self::HEADER_CONSUMER => $consumerKey,
            self::HEADER_TIMESTAMP => (string)$timestamp,
            self::HEADER_SIGNATURE => $this->sign($consumerSecret, $method, $path, $timestamp),
        ];
    }
}
