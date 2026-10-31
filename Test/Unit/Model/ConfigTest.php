<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model;

use Faccelerate\Connect\Model\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private ScopeConfigInterface&Stub $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->config = $this->configWith($this->createStub(WriterInterface::class), $this->createStub(TypeListInterface::class));
    }

    private function configWith(WriterInterface $writer, TypeListInterface $cacheTypeList): Config
    {
        return new Config($this->scopeConfig, $writer, $cacheTypeList);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function givenConfig(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function apiBaseUrlProvider(): array
    {
        return [
            'production' => ['production', '', 'https://api.faccelerate.ai'],
            'staging' => ['staging', '', 'https://api-staging.faccelerate.ai'],
            'custom' => ['custom', 'http://host.docker.internal:8787', 'http://host.docker.internal:8787'],
            'custom trailing slash' => ['custom', 'http://localhost:8787/', 'http://localhost:8787'],
            'custom several slashes and spaces' => ['custom', ' https://dev.example.com// ', 'https://dev.example.com'],
            'custom without url' => ['custom', '', 'https://api.faccelerate.ai'],
            'unknown environment' => ['moon', '', 'https://api.faccelerate.ai'],
            'unset environment' => ['', '', 'https://api.faccelerate.ai'],
            'custom url ignored in production' => ['production', 'http://localhost:8787', 'https://api.faccelerate.ai'],
        ];
    }

    #[DataProvider('apiBaseUrlProvider')]
    public function testApiBaseUrl(string $environment, string $customUrl, string $expected): void
    {
        $this->givenConfig([
            Config::PATH_ENVIRONMENT => $environment,
            Config::PATH_CUSTOM_API_URL => $customUrl,
        ]);

        self::assertSame($expected, $this->config->getApiBaseUrl());
    }

    public function testFlagsAreReadPerStore(): void
    {
        $flags = [
            Config::PATH_ENABLED . '|3' => true,
            Config::PATH_LOAD_ON_CHECKOUT . '|3' => false,
            Config::PATH_PURCHASE_EVENT . '|3' => true,
        ];
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, string $scope, $storeId): bool => $scope === 'store'
                && ($flags[$path . '|' . $storeId] ?? false)
        );

        self::assertTrue($this->config->isEnabled(3));
        self::assertFalse($this->config->loadOnCheckout(3));
        self::assertTrue($this->config->purchaseEventEnabled(3));
        self::assertFalse($this->config->isEnabled(4));
    }

    public function testCroKeyIsTrimmedAndNeverNull(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            [Config::PATH_CRO_KEY, 'store', 2, "  fx123 \n"],
            [Config::PATH_CRO_KEY, 'store', 5, null],
        ]);

        self::assertSame('fx123', $this->config->getCroKey(2));
        self::assertSame('', $this->config->getCroKey(5));
    }

    public function testConnectionGettersNormaliseEmptyValues(): void
    {
        $this->givenConfig([
            Config::PATH_INTEGRATION_ID => '',
            Config::PATH_STATUS => 'garbage',
            Config::PATH_WORKSPACE_NAME => '',
            Config::PATH_CONNECT_URL => ' ',
        ]);

        self::assertNull($this->config->getIntegrationId());
        self::assertSame(Config::STATUS_NONE, $this->config->getStatus());
        self::assertNull($this->config->getWorkspaceName());
        self::assertNull($this->config->getConnectUrl());
    }

    public function testConnectionGettersReturnStoredValues(): void
    {
        $this->givenConfig([
            Config::PATH_INTEGRATION_ID => '7',
            Config::PATH_STATUS => 'connected',
            Config::PATH_WORKSPACE_NAME => 'Shoeby',
            Config::PATH_CONNECT_URL => 'https://app.faccelerate.ai/connect/magento?code=abc',
        ]);

        self::assertSame(7, $this->config->getIntegrationId());
        self::assertSame(Config::STATUS_CONNECTED, $this->config->getStatus());
        self::assertSame('Shoeby', $this->config->getWorkspaceName());
        self::assertSame('https://app.faccelerate.ai/connect/magento?code=abc', $this->config->getConnectUrl());
    }

    public function testSettersWriteAtDefaultScopeAndCleanTheConfigCache(): void
    {
        $saved = [];
        $writer = $this->createStub(WriterInterface::class);
        $cache = $this->createMock(TypeListInterface::class);
        $writer->method('save')->willReturnCallback(
            static function (string $path, string $value) use (&$saved): void {
                $saved[$path] = $value;
            }
        );
        $cache->expects(self::exactly(4))->method('cleanType')->with('config');
        $config = $this->configWith($writer, $cache);

        $config->setIntegrationId(7);
        $config->setStatus(Config::STATUS_PENDING);
        $config->setWorkspaceName('Shoeby');
        $config->setCroKey('fx123');

        self::assertSame([
            Config::PATH_INTEGRATION_ID => '7',
            Config::PATH_STATUS => 'pending',
            Config::PATH_WORKSPACE_NAME => 'Shoeby',
            Config::PATH_CRO_KEY => 'fx123',
        ], $saved);
    }

    public function testSettingNullDeletesTheValue(): void
    {
        $deleted = [];
        $writer = $this->createMock(WriterInterface::class);
        $writer->expects(self::never())->method('save');
        $writer->method('delete')->willReturnCallback(
            static function (string $path) use (&$deleted): void {
                $deleted[] = $path;
            }
        );

        $config = $this->configWith($writer, $this->createStub(TypeListInterface::class));
        $config->setConnectUrl(null);
        $config->setCroKey('');

        self::assertSame([Config::PATH_CONNECT_URL, Config::PATH_CRO_KEY], $deleted);
    }

    public function testClearConnectionResetsEverything(): void
    {
        $deleted = [];
        $writer = $this->createMock(WriterInterface::class);
        $cache = $this->createMock(TypeListInterface::class);
        $writer->method('delete')->willReturnCallback(
            static function (string $path) use (&$deleted): void {
                $deleted[] = $path;
            }
        );
        $writer->expects(self::once())->method('save')->with(Config::PATH_STATUS, Config::STATUS_NONE);
        $cache->expects(self::once())->method('cleanType')->with('config');

        $this->configWith($writer, $cache)->clearConnection();

        self::assertEqualsCanonicalizing([
            Config::PATH_INTEGRATION_ID,
            Config::PATH_WORKSPACE_NAME,
            Config::PATH_CONNECT_URL,
            Config::PATH_CRO_KEY,
        ], $deleted);
    }
}
