<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model\Csp;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Csp\CustomHostCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomHostCollectorTest extends TestCase
{
    private function collector(string $environment, string $apiBaseUrl): CustomHostCollector
    {
        $config = $this->createStub(Config::class);
        $config->method('getEnvironment')->willReturn($environment);
        $config->method('getApiBaseUrl')->willReturn($apiBaseUrl);

        return new CustomHostCollector($config);
    }

    public function testAddsTheCustomOriginToThreeDirectives(): void
    {
        $existing = new FetchPolicy('default-src', false, ['https://example.com']);

        $policies = $this->collector(Config::ENV_CUSTOM, 'http://host.docker.internal:8787')->collect([$existing]);

        self::assertCount(4, $policies);
        self::assertSame($existing, $policies[0]);
        $byId = [];
        foreach (array_slice($policies, 1) as $policy) {
            $byId[$policy->getId()] = $policy->getHostSources();
        }
        self::assertSame([
            'script-src' => ['http://host.docker.internal:8787'],
            'connect-src' => ['http://host.docker.internal:8787'],
            'img-src' => ['http://host.docker.internal:8787'],
        ], $byId);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nothingToAddProvider(): array
    {
        return [
            'production' => [Config::ENV_PRODUCTION, 'https://api.faccelerate.ai'],
            'staging' => [Config::ENV_STAGING, 'https://api-staging.faccelerate.ai'],
            'custom with a non-http url' => [Config::ENV_CUSTOM, 'javascript:alert(1)'],
            'custom with garbage' => [Config::ENV_CUSTOM, 'not a url'],
        ];
    }

    #[DataProvider('nothingToAddProvider')]
    public function testLeavesPoliciesAloneOtherwise(string $environment, string $apiBaseUrl): void
    {
        self::assertSame([], $this->collector($environment, $apiBaseUrl)->collect([]));
    }
}
