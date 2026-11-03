<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Single source of truth for the module's configuration (see CONTRACT.md).
 *
 * Reads go through ScopeConfigInterface. Internal connection values and the CRO
 * key are written at default scope and the config cache is cleaned afterwards.
 */
class Config
{
    public const ENV_PRODUCTION = 'production';
    public const ENV_STAGING = 'staging';
    public const ENV_CUSTOM = 'custom';

    public const STATUS_NONE = 'none';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';

    public const API_URL_PRODUCTION = 'https://api.faccelerate.ai';
    public const API_URL_STAGING = 'https://api-staging.faccelerate.ai';

    public const PATH_ENABLED = 'faccelerate/general/enabled';
    public const PATH_ENVIRONMENT = 'faccelerate/general/environment';
    public const PATH_CUSTOM_API_URL = 'faccelerate/general/custom_api_url';
    public const PATH_CRO_KEY = 'faccelerate/general/cro_key';
    public const PATH_LOAD_ON_CHECKOUT = 'faccelerate/storefront/load_on_checkout';
    public const PATH_PURCHASE_EVENT = 'faccelerate/storefront/purchase_event';
    public const PATH_FUNNEL_EVENTS = 'faccelerate/storefront/funnel_events';
    public const PATH_INTEGRATION_ID = 'faccelerate/connection/integration_id';
    public const PATH_STATUS = 'faccelerate/connection/status';
    public const PATH_WORKSPACE_NAME = 'faccelerate/connection/workspace_name';
    public const PATH_CONNECT_URL = 'faccelerate/connection/connect_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $writer,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * One of the ENV_* constants; anything unknown counts as production.
     */
    public function getEnvironment(): string
    {
        $environment = (string)$this->scopeConfig->getValue(self::PATH_ENVIRONMENT);

        return in_array($environment, [self::ENV_STAGING, self::ENV_CUSTOM], true)
            ? $environment
            : self::ENV_PRODUCTION;
    }

    /**
     * The custom API URL without trailing slash, empty when none is set.
     */
    public function getCustomApiUrl(): string
    {
        return rtrim(trim((string)$this->scopeConfig->getValue(self::PATH_CUSTOM_API_URL)), '/');
    }

    /**
     * API base URL without trailing slash. Environment "custom" without a URL
     * falls back to production, so a half-filled setting never yields a broken URL.
     */
    public function getApiBaseUrl(): string
    {
        return match ($this->getEnvironment()) {
            self::ENV_STAGING => self::API_URL_STAGING,
            self::ENV_CUSTOM => $this->getCustomApiUrl() ?: self::API_URL_PRODUCTION,
            default => self::API_URL_PRODUCTION,
        };
    }

    public function getCroKey(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::PATH_CRO_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function loadOnCheckout(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_LOAD_ON_CHECKOUT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function purchaseEventEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_PURCHASE_EVENT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function funnelEventsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_FUNNEL_EVENTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getIntegrationId(): ?int
    {
        $id = (int)$this->scopeConfig->getValue(self::PATH_INTEGRATION_ID);

        return $id > 0 ? $id : null;
    }

    public function setIntegrationId(?int $integrationId): void
    {
        $this->save(self::PATH_INTEGRATION_ID, $integrationId === null ? null : (string)$integrationId);
    }

    /**
     * One of the STATUS_* constants; anything unknown counts as none.
     */
    public function getStatus(): string
    {
        $status = (string)$this->scopeConfig->getValue(self::PATH_STATUS);

        return in_array($status, [self::STATUS_PENDING, self::STATUS_CONNECTED], true)
            ? $status
            : self::STATUS_NONE;
    }

    public function setStatus(string $status): void
    {
        $this->save(self::PATH_STATUS, $status);
    }

    public function getWorkspaceName(): ?string
    {
        $name = trim((string)$this->scopeConfig->getValue(self::PATH_WORKSPACE_NAME));

        return $name !== '' ? $name : null;
    }

    public function setWorkspaceName(?string $workspaceName): void
    {
        $this->save(self::PATH_WORKSPACE_NAME, $workspaceName);
    }

    public function getConnectUrl(): ?string
    {
        $url = trim((string)$this->scopeConfig->getValue(self::PATH_CONNECT_URL));

        return $url !== '' ? $url : null;
    }

    public function setConnectUrl(?string $connectUrl): void
    {
        $this->save(self::PATH_CONNECT_URL, $connectUrl);
    }

    /**
     * Writes the CRO key at default scope (null or empty removes it).
     */
    public function setCroKey(?string $croKey): void
    {
        $this->save(self::PATH_CRO_KEY, $croKey);
    }

    /**
     * Forgets everything about a connection: integration, status, workspace, connect URL and CRO key.
     */
    public function clearConnection(): void
    {
        $this->writer->delete(self::PATH_INTEGRATION_ID);
        $this->writer->delete(self::PATH_WORKSPACE_NAME);
        $this->writer->delete(self::PATH_CONNECT_URL);
        $this->writer->delete(self::PATH_CRO_KEY);
        $this->writer->save(self::PATH_STATUS, self::STATUS_NONE);
        $this->cleanCache();
    }

    private function save(string $path, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->writer->delete($path);
        } else {
            $this->writer->save($path, $value);
        }
        $this->cleanCache();
    }

    private function cleanCache(): void
    {
        $this->cacheTypeList->cleanType(ConfigCacheType::TYPE_IDENTIFIER);
    }
}
