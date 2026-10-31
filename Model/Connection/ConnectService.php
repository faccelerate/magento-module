<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Connection;

use Faccelerate\Connect\Model\Api\ApiException;
use Faccelerate\Connect\Model\Api\Client;
use Faccelerate\Connect\Model\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The Connect / Check status / Disconnect flow of CONTRACT.md ("Connecting"), without any UI.
 */
class ConnectService
{
    private const MODULE_NAME = 'Faccelerate_Connect';
    /** Used when composer.json carries no version (a checkout instead of a tagged install). */
    private const FALLBACK_MODULE_VERSION = '0.1.0';
    private const FULL_PAGE_CACHE_TYPE = 'full_page';

    public function __construct(
        private readonly Client $client,
        private readonly IntegrationProvisioner $provisioner,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductMetadataInterface $productMetadata,
        private readonly PackageInfo $packageInfo,
        private readonly TypeListInterface $cacheTypeList,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Provisions the integration, registers the store with Faccelerate and remembers the pending state.
     *
     * @param string $adminReturnUrl The config page the Faccelerate app sends the admin user back to
     * @return string The URL where the admin user picks a workspace
     * @throws LocalizedException
     */
    public function connect(string $adminReturnUrl): string
    {
        $credentials = $this->provisioner->provision();
        $store = $this->storeManager->getDefaultStoreView() ?? current($this->storeManager->getStores());
        if (!$store) {
            throw new LocalizedException(new Phrase('This shop has no store view to connect.'));
        }

        $response = $this->client->register([
            'store_base_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_WEB),
            'store_name' => $store->getGroup()->getName(),
            'magento_version' => $this->productMetadata->getVersion(),
            'module_version' => $this->packageInfo->getVersion(self::MODULE_NAME) ?: self::FALLBACK_MODULE_VERSION,
            'consumer_key' => $credentials->consumerKey,
            'consumer_secret' => $credentials->consumerSecret,
            'access_token' => $credentials->accessToken,
            'access_token_secret' => $credentials->accessTokenSecret,
            'admin_return_url' => $adminReturnUrl,
        ]);

        $this->config->setStatus(Config::STATUS_PENDING);
        $this->config->setConnectUrl($response['connect_url']);

        return $response['connect_url'];
    }

    /**
     * Asks Faccelerate where the connection stands and saves the answer.
     *
     * @return array{status: string, workspace_name: ?string} The state as saved (re-reading the config in the
     *     same request can still return the old values)
     * @throws LocalizedException
     */
    public function refresh(): array
    {
        $credentials = $this->provisioner->getCredentials();
        if ($credentials === null) {
            // No integration: nothing can be connected, whatever the stored status says.
            $this->config->clearConnection();

            return ['status' => Config::STATUS_NONE, 'workspace_name' => null];
        }

        $link = $this->client->link($credentials->consumerKey, $credentials->consumerSecret);

        switch ($link['status'] ?? '') {
            case 'connected':
                $workspaceName = $this->stringOrNull($link['workspace_name'] ?? null);
                $this->config->setStatus(Config::STATUS_CONNECTED);
                $this->config->setWorkspaceName($workspaceName);
                $croKey = $this->stringOrNull($link['cro_key'] ?? null);
                if ($croKey !== null) {
                    $this->config->setCroKey($croKey);
                }
                $this->config->setConnectUrl(null);
                $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE_TYPE);

                return ['status' => Config::STATUS_CONNECTED, 'workspace_name' => $workspaceName];
            case 'pending':
                $this->config->setStatus(Config::STATUS_PENDING);

                return ['status' => Config::STATUS_PENDING, 'workspace_name' => null];
            case 'disconnected':
                // Disconnected on the Faccelerate side. The CRO key stays: it may have been typed in by hand.
                $this->config->setStatus(Config::STATUS_NONE);
                $this->config->setWorkspaceName(null);
                $this->config->setConnectUrl(null);

                return ['status' => Config::STATUS_NONE, 'workspace_name' => null];
        }

        throw new ApiException(new Phrase('Faccelerate answered with an unknown connection status.'));
    }

    /**
     * Tells Faccelerate (best effort), deletes the integration and forgets the connection.
     * The local clean-up happens even when Faccelerate cannot be reached.
     */
    public function disconnect(): void
    {
        try {
            $credentials = $this->provisioner->getCredentials();
            if ($credentials !== null) {
                $this->client->disconnect($credentials->consumerKey, $credentials->consumerSecret);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Faccelerate: the disconnect call failed, removing the integration anyway: '
                . $e->getMessage());
        }

        $this->provisioner->remove();
        $this->config->clearConnection();
        $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE_TYPE);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
