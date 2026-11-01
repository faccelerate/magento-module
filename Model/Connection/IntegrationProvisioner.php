<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Connection;

use Faccelerate\Connect\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration;

/**
 * Creates (or reuses) the Magento integration "Faccelerate", activates it without any admin popup and
 * reads its OAuth1 credentials, the same way Magento's own "Activate" button does (TokensDialog):
 * createAccessToken() runs the verifier, request and access token steps in one go, then the status is set to active.
 */
class IntegrationProvisioner
{
    public const INTEGRATION_NAME = 'Faccelerate';

    /**
     * Exactly the ACL resources of CONTRACT.md ("incl. actions/view" are Magento_Sales::actions and
     * Magento_Sales::actions_view, which the order read API checks).
     */
    public const RESOURCES = [
        'Magento_Backend::store',
        'Magento_Config::config',
        'Magento_Catalog::products',
        'Magento_Catalog::categories',
        'Magento_Sales::sales_order',
        'Magento_Sales::actions',
        'Magento_Sales::actions_view',
        'Magento_Cms::page',
        'Magento_Cms::block',
    ];

    public function __construct(
        private readonly IntegrationServiceInterface $integrationService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly OauthServiceInterface $oauthService,
        private readonly Config $config
    ) {
    }

    /**
     * Makes sure the active integration with an access token exists and returns its credentials.
     *
     * @throws LocalizedException
     */
    public function provision(): Credentials
    {
        $integration = $this->find();
        if ($integration === null) {
            // The service plugin saves the permissions from the "resource" key; without it they would be emptied.
            $integration = $this->integrationService->create([
                Integration::NAME => self::INTEGRATION_NAME,
                Integration::SETUP_TYPE => Integration::TYPE_MANUAL,
                Integration::STATUS => Integration::STATUS_INACTIVE,
                'resource' => self::RESOURCES,
            ]);
        }

        $integrationId = (int)$integration->getId();
        $consumerId = (int)$integration->getConsumerId();
        $this->authorizationService->grantPermissions($integrationId, self::RESOURCES);

        $token = $this->accessToken($consumerId);
        if ($integration->getStatus() !== Integration::STATUS_ACTIVE) {
            $this->integrationService->update([
                Integration::ID => $integrationId,
                Integration::NAME => self::INTEGRATION_NAME,
                Integration::STATUS => Integration::STATUS_ACTIVE,
                'resource' => self::RESOURCES,
            ]);
        }

        $this->config->setIntegrationId($integrationId);

        $consumer = $this->oauthService->loadConsumer($consumerId);

        return new Credentials(
            (string)$consumer->getKey(),
            (string)$consumer->getSecret(),
            (string)$token->getToken(),
            (string)$token->getSecret()
        );
    }

    /**
     * The credentials of the existing integration, or null when there is none (never connected, or deleted by hand).
     * Only the consumer pair is guaranteed to be filled in.
     */
    public function getCredentials(): ?Credentials
    {
        $integration = $this->find();
        if ($integration === null) {
            return null;
        }
        $consumer = $this->oauthService->loadConsumer((int)$integration->getConsumerId());
        if (!$consumer->getId()) {
            return null;
        }
        $token = $this->oauthService->getAccessToken((int)$integration->getConsumerId());

        return new Credentials(
            (string)$consumer->getKey(),
            (string)$consumer->getSecret(),
            $token ? (string)$token->getToken() : '',
            $token ? (string)$token->getSecret() : ''
        );
    }

    /**
     * Deletes the integration with its permissions, consumer and tokens (this revokes the token).
     */
    public function remove(): void
    {
        $integration = $this->find();
        if ($integration === null) {
            return;
        }
        $consumerId = (int)$integration->getConsumerId();
        $this->integrationService->delete((int)$integration->getId());
        if ($consumerId > 0) {
            try {
                $this->oauthService->deleteConsumer($consumerId);
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            } catch (LocalizedException) {
                // Already gone with the integration.
            }
        }
    }

    private function find(): ?Integration
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);

        return $integration->getId() ? $integration : null;
    }

    /**
     * @return \Magento\Integration\Model\Oauth\Token
     * @throws LocalizedException
     */
    private function accessToken(int $consumerId): \Magento\Integration\Model\Oauth\Token
    {
        // Returns false and leaves things alone when a token already exists.
        $this->oauthService->createAccessToken($consumerId, false);
        $token = $this->oauthService->getAccessToken($consumerId);
        if (!$token) {
            // A half-finished earlier attempt left a verifier or request token behind: start over.
            $this->oauthService->createAccessToken($consumerId, true);
            $token = $this->oauthService->getAccessToken($consumerId);
        }
        if (!$token) {
            throw new LocalizedException(new Phrase('The Faccelerate integration could not get an access token.'));
        }

        return $token;
    }
}
