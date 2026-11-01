<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model\Api;

use Faccelerate\Connect\Model\Api\Signer;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    private const SECRET = 'consumer-secret-123';
    private const TIMESTAMP = 1730000000;

    private Signer $signer;

    protected function setUp(): void
    {
        $this->signer = new Signer();
    }

    /**
     * Vectors computed independently with:
     * printf 'GET\n/x/magento/link\n1730000000' | openssl dgst -sha256 -hmac 'consumer-secret-123' -hex
     */
    public function testSignsMethodPathAndTimestampWithTheConsumerSecret(): void
    {
        self::assertSame(
            'e67a6fd5152281ebc8865e40c7a96d415b479a49137b84ca401df678aea00ea0',
            $this->signer->sign(self::SECRET, 'GET', '/x/magento/link', self::TIMESTAMP)
        );
        self::assertSame(
            '92bda8dcdb977bf7fe94f75b15b4d236205d51fa72e087a75eccc186f48035d2',
            $this->signer->sign(self::SECRET, 'POST', '/x/magento/disconnect', self::TIMESTAMP)
        );
    }

    public function testMethodIsUppercased(): void
    {
        self::assertSame(
            $this->signer->sign(self::SECRET, 'GET', '/x/magento/link', self::TIMESTAMP),
            $this->signer->sign(self::SECRET, 'get', '/x/magento/link', self::TIMESTAMP)
        );
    }

    public function testEveryInputChangesTheSignature(): void
    {
        $base = $this->signer->sign(self::SECRET, 'GET', '/x/magento/link', self::TIMESTAMP);

        self::assertNotSame($base, $this->signer->sign('other', 'GET', '/x/magento/link', self::TIMESTAMP));
        self::assertNotSame($base, $this->signer->sign(self::SECRET, 'POST', '/x/magento/link', self::TIMESTAMP));
        self::assertNotSame($base, $this->signer->sign(self::SECRET, 'GET', '/x/magento/other', self::TIMESTAMP));
        self::assertNotSame($base, $this->signer->sign(self::SECRET, 'GET', '/x/magento/link', self::TIMESTAMP + 1));
    }

    public function testHeaders(): void
    {
        self::assertSame(
            [
                'X-Faccelerate-Consumer' => 'ck_1',
                'X-Faccelerate-Timestamp' => '1730000000',
                'X-Faccelerate-Signature' => 'e67a6fd5152281ebc8865e40c7a96d415b479a49137b84ca401df678aea00ea0',
            ],
            $this->signer->headers('ck_1', self::SECRET, 'GET', '/x/magento/link', self::TIMESTAMP)
        );
    }
}
