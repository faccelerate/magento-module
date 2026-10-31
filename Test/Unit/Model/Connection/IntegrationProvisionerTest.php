<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model\Connection;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Connection\IntegrationProvisioner;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Integration\Model\Oauth\Consumer;
use Magento\Integration\Model\Oauth\Token;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class IntegrationProvisionerTest extends TestCase
{
    private IntegrationServiceInterface&MockObject $integrationService;
    private AuthorizationServiceInterface&MockObject $authorizationService;
    private OauthServiceInterface&MockObject $oauthService;
    private Config&MockObject $config;
    private IntegrationProvisioner $provisioner;

    protected function setUp(): void
    {
        $this->integrationService = $this->createMock(IntegrationServiceInterface::class);
        $this->authorizationService = $this->createMock(AuthorizationServiceInterface::class);
        $this->oauthService = $this->createMock(OauthServiceInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->provisioner = new IntegrationProvisioner(
            $this->integrationService,
            $this->authorizationService,
            $this->oauthService,
            $this->config
        );
    }

    /**
     * Real models without their heavy constructors: the magic getters (getConsumerId, getToken) need real objects.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    private function model(string $class, array $data): object
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $model->setData($data);

        return $model;
    }

    private function integration(int $id, int $consumerId, int $status): Integration
    {
        return $this->model(
            Integration::class,
            ['id' => $id ?: null, 'consumer_id' => $consumerId, 'status' => $status]
        );
    }

    private function token(string $token, string $secret): Token
    {
        return $this->model(Token::class, ['token' => $token, 'secret' => $secret]);
    }

    private function consumer(): Consumer
    {
        $consumer = $this->createStub(Consumer::class);
        $consumer->method('getId')->willReturn(7);
        $consumer->method('getKey')->willReturn('consumer-key');
        $consumer->method('getSecret')->willReturn('consumer-secret');

        return $consumer;
    }

    public function testCreatesGrantsActivatesAndReadsAllFourCredentials(): void
    {
        $this->integrationService->method('findByName')->with('Faccelerate')
            ->willReturn($this->integration(0, 0, 0));
        $this->integrationService->expects(self::once())->method('create')
            ->with(self::callback(static fn (array $data): bool => $data['name'] === 'Faccelerate'
                && $data['resource'] === IntegrationProvisioner::RESOURCES))
            ->willReturn($this->integration(5, 7, Integration::STATUS_INACTIVE));
        $this->authorizationService->expects(self::once())->method('grantPermissions')
            ->with(5, IntegrationProvisioner::RESOURCES);
        $this->oauthService->expects(self::once())->method('createAccessToken')->with(7, false);
        $this->oauthService->method('getAccessToken')->with(7)->willReturn($this->token('at', 'ats'));
        $this->oauthService->method('loadConsumer')->with(7)->willReturn($this->consumer());
        $this->integrationService->expects(self::once())->method('update')
            ->with(self::callback(static fn (array $data): bool => $data['integration_id'] === 5
                && $data['status'] === Integration::STATUS_ACTIVE
                && $data['resource'] === IntegrationProvisioner::RESOURCES));
        $this->config->expects(self::once())->method('setIntegrationId')->with(5);

        $credentials = $this->provisioner->provision();

        self::assertSame('consumer-key', $credentials->consumerKey);
        self::assertSame('consumer-secret', $credentials->consumerSecret);
        self::assertSame('at', $credentials->accessToken);
        self::assertSame('ats', $credentials->accessTokenSecret);
    }

    public function testReusesAnActiveIntegrationWithoutCreatingOrUpdating(): void
    {
        $this->integrationService->method('findByName')
            ->willReturn($this->integration(5, 7, Integration::STATUS_ACTIVE));
        $this->integrationService->expects(self::never())->method('create');
        $this->integrationService->expects(self::never())->method('update');
        $this->oauthService->method('getAccessToken')->willReturn($this->token('at', 'ats'));
        $this->oauthService->method('loadConsumer')->willReturn($this->consumer());

        self::assertSame('at', $this->provisioner->provision()->accessToken);
    }

    public function testStartsOverWhenOnlyAHalfFinishedTokenExists(): void
    {
        $this->integrationService->method('findByName')
            ->willReturn($this->integration(5, 7, Integration::STATUS_ACTIVE));
        $this->oauthService->expects(self::exactly(2))->method('createAccessToken')
            ->willReturnCallback(static fn (int $consumerId, bool $clear): bool => $clear);
        $this->oauthService->method('getAccessToken')->willReturnOnConsecutiveCalls(false, $this->token('at', 'ats'));
        $this->oauthService->method('loadConsumer')->willReturn($this->consumer());

        self::assertSame('ats', $this->provisioner->provision()->accessTokenSecret);
    }

    public function testFailsWhenNoAccessTokenCanBeIssued(): void
    {
        $this->integrationService->method('findByName')
            ->willReturn($this->integration(5, 7, Integration::STATUS_ACTIVE));
        $this->oauthService->method('getAccessToken')->willReturn(false);
        $this->config->expects(self::never())->method('setIntegrationId');

        $this->expectException(LocalizedException::class);
        $this->provisioner->provision();
    }

    public function testGetCredentialsIsNullWithoutIntegration(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integration(0, 0, 0));

        self::assertNull($this->provisioner->getCredentials());
    }

    public function testGetCredentialsWorksWithoutAccessToken(): void
    {
        $this->integrationService->method('findByName')
            ->willReturn($this->integration(5, 7, Integration::STATUS_ACTIVE));
        $this->oauthService->method('loadConsumer')->willReturn($this->consumer());
        $this->oauthService->method('getAccessToken')->willReturn(false);

        $credentials = $this->provisioner->getCredentials();

        self::assertSame('consumer-key', $credentials?->consumerKey);
        self::assertSame('', $credentials?->accessToken);
    }

    public function testRemoveDeletesTheIntegrationThenItsConsumer(): void
    {
        $this->integrationService->method('findByName')
            ->willReturn($this->integration(5, 7, Integration::STATUS_ACTIVE));
        $this->integrationService->expects(self::once())->method('delete')->with(5);
        $this->oauthService->expects(self::once())->method('deleteConsumer')->with(7);

        $this->provisioner->remove();
    }

    public function testRemoveDoesNothingWithoutIntegration(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integration(0, 0, 0));
        $this->integrationService->expects(self::never())->method('delete');

        $this->provisioner->remove();
    }
}
