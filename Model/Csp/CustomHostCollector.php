<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Csp;

use Faccelerate\Connect\Model\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Allows the custom Faccelerate API origin (local development) in the CSP.
 * The two fixed hosts live in etc/csp_whitelist.xml.
 */
class CustomHostCollector implements PolicyCollectorInterface
{
    private const DIRECTIVES = ['script-src', 'connect-src', 'img-src'];

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        $origin = $this->getCustomOrigin();
        if ($origin === null) {
            return $defaultPolicies;
        }

        $policies = $defaultPolicies;
        foreach (self::DIRECTIVES as $directive) {
            $policies[] = new FetchPolicy($directive, false, [$origin]);
        }

        return $policies;
    }

    private function getCustomOrigin(): ?string
    {
        if ($this->config->getEnvironment() !== Config::ENV_CUSTOM) {
            return null;
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- plain split of a foreign URL
        $parts = parse_url($this->config->getApiBaseUrl());
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || !in_array($parts['scheme'], ['http', 'https'], true)
        ) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
