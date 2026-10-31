<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model\Connection;

use Faccelerate\Connect\Model\Api\ApiException;
use Faccelerate\Connect\Model\Api\Client;
use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Connection\ConnectService;
use Faccelerate\Connect\Model\Connection\Credentials;
use Faccelerate\Connect\Model\Connection\IntegrationProvisioner;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Phrase;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class ConnectServiceTest extends TestCase
{
    private Client&MockObject $client;
    private IntegrationProvisioner&MockObject $provisioner;
    private Config&MockObject $config;
    private StoreManagerInterface&MockObject $storeManager;
    private ProductMetadataInterface&MockObject $productMetadata;
    private PackageInfo&MockObject $packageInfo;
    private TypeListInterface&MockObject $cacheTypeList;
    private LoggerInterface&MockObject $logger;
    private ConnectService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->provisioner = $this->createMock(IntegrationProvisioner::class);
        $this->config = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->packageInfo = $this->createMock(PackageInfo::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ConnectService(
            $this->client,
            $this->provisioner,
            $this->config,
            $this->storeManager,
            $this->productMetadata,
            $this->packageInfo,
            $this->cacheTypeList,
            $this->logger
        );
    }

    private function credentials(): Credentials
    {
        return new Credentials('ck', 'cs', 'at', 'ats');
    }

    private function givenStore(): void
    {
        $group = $this->createMock(Group::class);
        $group->method('getName')->willReturn('Main Website Store');
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/');
        $store->method('getGroup')->willReturn($group);
        $this->storeManager->method('getDefaultStoreView')->willReturn($store);
    }

    public function testConnectProvisionsRegistersAndSavesPendingState(): void
    {
        $this->givenStore();
        $this->productMetadata->method('getVersion')->willReturn('2.4.9');
        $this->packageInfo->method('getVersion')->with('Faccelerate_Connect')->willReturn('1.2.3');
        $this->provisioner->expects(self::once())->method('provision')->willReturn($this->credentials());
        $this->client->expects(self::once())->method('register')->with([
            'store_base_url' => 'https://shop.example.com/',
            'store_name' => 'Main Website Store',
            'magento_version' => '2.4.9',
            'module_version' => '1.2.3',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
            'access_token' => 'at',
            'access_token_secret' => 'ats',
            'admin_return_url' => 'https://shop.example.com/admin/faccelerate/',
        ])->willReturn([
            'link_id' => 'l1',
            'connect_url' => 'https://app.faccelerate.ai/connect/magento?code=abc',
            'expires_at' => '2026-11-01T12:00:00Z',
        ]);
        $this->config->expects(self::once())->method('setStatus')->with(Config::STATUS_PENDING);
        $this->config->expects(self::once())->method('setConnectUrl')
            ->with('https://app.faccelerate.ai/connect/magento?code=abc');

        self::assertSame(
            'https://app.faccelerate.ai/connect/magento?code=abc',
            $this->service->connect('https://shop.example.com/admin/faccelerate/')
        );
    }

    public function testConnectFallsBackToTheModuleVersionConstantWhenComposerHasNone(): void
    {
        $this->givenStore();
        $this->packageInfo->method('getVersion')->willReturn('');
        $this->provisioner->method('provision')->willReturn($this->credentials());
        $this->client->expects(self::once())->method('register')
            ->with(self::callback(static fn (array $payload): bool => $payload['module_version'] === '0.1.0'))
            ->willReturn(['connect_url' => 'https://app.faccelerate.ai/connect/magento?code=abc']);

        $this->service->connect('https://shop.example.com/admin/');
    }

    public function testConnectLeavesTheStateAloneWhenRegisteringFails(): void
    {
        $this->givenStore();
        $this->provisioner->method('provision')->willReturn($this->credentials());
        $this->client->method('register')->willThrowException(new ApiException(new Phrase('Nope')));
        $this->config->expects(self::never())->method('setStatus');
        $this->config->expects(self::never())->method('setConnectUrl');

        $this->expectException(ApiException::class);
        $this->service->connect('https://shop.example.com/admin/');
    }

    public function testRefreshSavesAConnectedWorkspace(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->expects(self::once())->method('link')->with('ck', 'cs')->willReturn([
            'status' => 'connected',
            'workspace_name' => 'Shoeby',
            'cro_key' => 'fx123',
            'environment' => 'production',
        ]);
        $this->config->expects(self::once())->method('setStatus')->with(Config::STATUS_CONNECTED);
        $this->config->expects(self::once())->method('setWorkspaceName')->with('Shoeby');
        $this->config->expects(self::once())->method('setCroKey')->with('fx123');
        $this->config->expects(self::once())->method('setConnectUrl')->with(null);
        $this->cacheTypeList->expects(self::once())->method('invalidate')->with('full_page');

        self::assertSame(
            ['status' => Config::STATUS_CONNECTED, 'workspace_name' => 'Shoeby'],
            $this->service->refresh()
        );
    }

    public function testRefreshKeepsAHandTypedCroKeyWhenTheApiSendsNone(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->method('link')->willReturn(
            ['status' => 'connected', 'workspace_name' => 'Shoeby', 'cro_key' => null]
        );
        $this->config->expects(self::never())->method('setCroKey');

        $this->service->refresh();
    }

    public function testRefreshKeepsPending(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->method('link')->willReturn(['status' => 'pending', 'workspace_name' => null]);
        $this->config->expects(self::once())->method('setStatus')->with(Config::STATUS_PENDING);
        $this->config->expects(self::never())->method('setCroKey');

        self::assertSame(
            ['status' => Config::STATUS_PENDING, 'workspace_name' => null],
            $this->service->refresh()
        );
    }

    public function testRefreshHandlesADisconnectionOnTheFaccelerateSide(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->method('link')->willReturn(['status' => 'disconnected']);
        $this->config->expects(self::once())->method('setStatus')->with(Config::STATUS_NONE);
        $this->config->expects(self::once())->method('setWorkspaceName')->with(null);
        $this->config->expects(self::never())->method('setCroKey');

        self::assertSame(
            ['status' => Config::STATUS_NONE, 'workspace_name' => null],
            $this->service->refresh()
        );
    }

    public function testRefreshWithoutIntegrationClearsTheConnectionAndSkipsTheApi(): void
    {
        $this->provisioner->method('getCredentials')->willReturn(null);
        $this->client->expects(self::never())->method('link');
        $this->config->expects(self::once())->method('clearConnection');

        self::assertSame(
            ['status' => Config::STATUS_NONE, 'workspace_name' => null],
            $this->service->refresh()
        );
    }

    public function testRefreshRejectsAnUnknownStatus(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->method('link')->willReturn(['status' => 'weird']);
        $this->config->expects(self::never())->method('setStatus');

        $this->expectException(ApiException::class);
        $this->service->refresh();
    }

    public function testDisconnectTellsFaccelerateThenDeletesTheIntegrationAndClears(): void
    {
        $calls = [];
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->expects(self::once())->method('disconnect')->with('ck', 'cs')
            ->willReturnCallback(static function () use (&$calls): array {
                $calls[] = 'api';

                return ['status' => 'disconnected'];
            });
        $this->provisioner->expects(self::once())->method('remove')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'remove';
            });
        $this->config->expects(self::once())->method('clearConnection')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'clear';
            });
        $this->cacheTypeList->expects(self::once())->method('invalidate')->with('full_page');

        $this->service->disconnect();

        self::assertSame(['api', 'remove', 'clear'], $calls);
    }

    public function testDisconnectStillCleansUpWhenFaccelerateCannotBeReached(): void
    {
        $this->provisioner->method('getCredentials')->willReturn($this->credentials());
        $this->client->method('disconnect')->willThrowException(new ApiException(new Phrase('Unreachable')));
        $this->logger->expects(self::once())->method('warning');
        $this->provisioner->expects(self::once())->method('remove');
        $this->config->expects(self::once())->method('clearConnection');

        $this->service->disconnect();
    }

    public function testDisconnectWithoutIntegrationSkipsTheApi(): void
    {
        $this->provisioner->method('getCredentials')->willReturn(null);
        $this->client->expects(self::never())->method('disconnect');
        $this->provisioner->expects(self::once())->method('remove');
        $this->config->expects(self::once())->method('clearConnection');

        $this->service->disconnect();
    }
}
