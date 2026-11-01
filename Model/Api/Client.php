<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Api;

use Faccelerate\Connect\Model\Config;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The module's three calls to the Faccelerate API (see CONTRACT.md, "Connecting").
 */
class Client
{
    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config,
        private readonly Signer $signer,
        private readonly Json $json
    ) {
    }

    /**
     * POST /x/magento/register (not signed: it carries the credentials itself).
     *
     * @param array<string, mixed> $payload
     * @return array{link_id: string, connect_url: string, expires_at: string}
     * @throws ApiException
     */
    public function register(array $payload): array
    {
        $response = $this->request('POST', '/x/magento/register', $this->json->serialize($payload));
        $connectUrl = $response['connect_url'] ?? null;
        if (!is_string($connectUrl) || $connectUrl === '') {
            throw new ApiException(new Phrase('Faccelerate did not return a connect URL.'));
        }

        return $response;
    }

    /**
     * GET /x/magento/link (signed).
     *
     * @return array{status: string, workspace_name: ?string, cro_key: ?string, environment: string}
     * @throws ApiException
     */
    public function link(string $consumerKey, string $consumerSecret): array
    {
        return $this->request('GET', '/x/magento/link', null, [$consumerKey, $consumerSecret]);
    }

    /**
     * POST /x/magento/disconnect (signed).
     *
     * @return array{status: string}
     * @throws ApiException
     */
    public function disconnect(string $consumerKey, string $consumerSecret): array
    {
        return $this->request('POST', '/x/magento/disconnect', '{}', [$consumerKey, $consumerSecret]);
    }

    /**
     * @param array{0: string, 1: string}|null $credentials Consumer key and secret when the call must be signed
     * @return array<string, mixed>
     * @throws ApiException
     */
    private function request(string $method, string $route, ?string $body, ?array $credentials = null): array
    {
        $url = $this->config->getApiBaseUrl() . $route;
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT_SECONDS);
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('Content-Type', 'application/json');

        if ($credentials !== null) {
            // The signed path is the real request path (a custom API URL may carry a prefix), without host and query.
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- plain split of a foreign URL
            $path = (string)parse_url($url, PHP_URL_PATH);
            $headers = $this->signer->headers($credentials[0], $credentials[1], $method, $path, $this->now());
            foreach ($headers as $name => $value) {
                $curl->addHeader($name, $value);
            }
        }

        try {
            if ($method === 'POST') {
                $curl->post($url, (string)$body);
            } else {
                $curl->get($url);
            }
        } catch (\Throwable $e) {
            throw new ApiException(new Phrase('Faccelerate could not be reached: %1', [$e->getMessage()]), $e);
        }

        return $this->parse($curl->getStatus(), (string)$curl->getBody());
    }

    /**
     * @return array<string, mixed>
     * @throws ApiException
     */
    private function parse(int $status, string $body): array
    {
        try {
            $data = $body === '' ? [] : $this->json->unserialize($body);
        } catch (\InvalidArgumentException) {
            $data = null;
        }

        if ($status < 200 || $status >= 300) {
            $error = is_array($data) && isset($data['error']) && is_string($data['error']) ? $data['error'] : '';
            throw new ApiException(
                $error !== ''
                    ? new Phrase('Faccelerate answered: %1 (HTTP %2)', [$error, $status])
                    : new Phrase('Faccelerate answered with HTTP %1.', [$status])
            );
        }
        if (!is_array($data)) {
            throw new ApiException(new Phrase('Faccelerate answered with something that is not JSON.'));
        }

        return $data;
    }

    private function now(): int
    {
        return time();
    }
}
