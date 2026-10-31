<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Block;

use Faccelerate\Connect\Model\Config;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Renders the CRO loader script tag in the head. The output depends only on the
 * store view and the page's action, never on the visitor, so it is safe for the
 * full page cache.
 */
class Loader extends Template
{
    private const CHECKOUT_PREFIXES = ['checkout_', 'multishipping_checkout_'];
    private const CHECKOUT_ALLOWED_ACTIONS = ['checkout_cart_index', 'checkout_onepage_success'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldRender(): bool
    {
        if (!$this->config->isEnabled() || $this->config->getCroKey() === '') {
            return false;
        }

        return $this->config->loadOnCheckout() || !$this->isCheckoutPage();
    }

    public function getLoaderUrl(): string
    {
        return $this->config->getApiBaseUrl() . '/x/cro/loader.js';
    }

    public function getScriptHtml(): string
    {
        return $this->secureRenderer->renderTag(
            'script',
            [
                'async' => '',
                'src' => $this->getLoaderUrl(),
                'data-fx' => $this->config->getCroKey(),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        return $this->shouldRender() ? parent::_toHtml() : '';
    }

    private function isCheckoutPage(): bool
    {
        $request = $this->getRequest();
        $action = $request instanceof Http ? (string)$request->getFullActionName() : '';
        if ($action === '' || in_array($action, self::CHECKOUT_ALLOWED_ACTIONS, true)) {
            return false;
        }
        foreach (self::CHECKOUT_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
