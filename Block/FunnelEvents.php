<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Block;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\FunnelPayload;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\Store;

/**
 * Sends the GA4 ecommerce funnel events Magento's own Google tag leaves out: view_item on product pages,
 * add_to_cart after an AJAX add to cart, and begin_checkout on the checkout. They go through gtag, so they
 * reach the same GA4 property as Magento's page views and purchases. The block's `event` argument (layout)
 * picks which script it renders.
 */
class FunnelEvents extends Template
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /**
     * Defines fxSend(name, params): it sends through gtag once the page's Google tag has its `config` in the
     * dataLayer. Magento's tag loads through RequireJS, often after these scripts, and gtag.js does not route an
     * event that comes before its config. Without a Google tag on the page it gives up after ten seconds.
     */
    private const SEND = 'window.dataLayer = window.dataLayer || [];'
        . ' var fxSend = function (name, params, tries) {'
        . ' var configured = window.dataLayer.some(function (entry) { return entry && entry[0] === "config"; });'
        . ' if (!configured) { if ((tries || 0) < 40) { setTimeout(function () { fxSend(name, params, (tries || 0) + 1); }, 250); } return; }'
        . ' (typeof window.gtag === "function" ? window.gtag : function () { window.dataLayer.push(arguments); })("event", name, params); };';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly FunnelPayload $funnelPayload,
        private readonly CatalogHelper $catalogHelper,
        private readonly CheckoutSession $checkoutSession,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getScriptHtml(): string
    {
        if (!$this->config->isEnabled()
            || !$this->config->funnelEventsEnabled()
            || $this->config->getCroKey() === ''
        ) {
            return '';
        }

        $js = match ((string)$this->getData('event')) {
            'view_item' => $this->viewItemJs(),
            'add_to_cart' => $this->addToCartJs(),
            'begin_checkout' => $this->beginCheckoutJs(),
            default => null,
        };

        return $js === null ? '' : $this->secureRenderer->renderTag('script', [], $js, false);
    }

    private function viewItemJs(): ?string
    {
        $product = $this->catalogHelper->getProduct();
        if ($product === null || !$product->getId()) {
            return null;
        }
        $price = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        $params = $this->funnelPayload->forProduct($product, $price, $this->currency());
        $current = $params['items'][0] + ['product_id' => (string)$product->getId()];
        $paramsJson = $this->json($params);
        $currentJson = $this->json($current);
        if ($paramsJson === null || $currentJson === null) {
            return null;
        }

        return '(function () { ' . self::SEND
            . ' window.fxCurrentItem = ' . $currentJson . ';'
            . ' fxSend("view_item", ' . $paramsJson . '); })();';
    }

    /**
     * Luma triggers `ajax:addToCart` on the document after a successful AJAX add to cart, with the product
     * ids and quantities. On a product page the item's name and price come from view_item.
     */
    private function addToCartJs(): ?string
    {
        $currency = $this->json($this->currency());
        if ($currency === null) {
            return null;
        }

        $send = self::SEND;

        return <<<JS
(function () {
    {$send}
    if (typeof window.require !== 'function') {
        return;
    }
    window.require(['jquery'], function (\$) {
        \$(document).on('ajax:addToCart', function (event, data) {
            var info = (data && data.productInfo) || [];
            var current = window.fxCurrentItem;
            var items = [];
            info.forEach(function (product) {
                var quantity = parseFloat(product.qty) || 1;
                if (current && String(current.product_id) === String(product.id)) {
                    items.push({item_id: current.item_id, item_name: current.item_name,
                        price: current.price, quantity: quantity});
                } else {
                    items.push({item_id: String((data && data.sku) || product.id), quantity: quantity});
                }
            });
            if (!items.length) {
                return;
            }
            var value = items.reduce(function (sum, item) {
                return sum + (item.price || 0) * item.quantity;
            }, 0);
            var params = {currency: {$currency}, items: items};
            if (value > 0) {
                params.value = Math.round(value * 100) / 100;
            }
            fxSend('add_to_cart', params);
        });
    });
})();
JS;
    }

    private function beginCheckoutJs(): ?string
    {
        $params = $this->funnelPayload->forCart($this->checkoutSession->getQuote());
        $json = $params === null ? null : $this->json($params);
        if ($json === null) {
            return null;
        }

        return '(function () { ' . self::SEND . ' fxSend("begin_checkout", ' . $json . '); })();';
    }

    private function currency(): string
    {
        $store = $this->_storeManager->getStore();

        return $store instanceof Store ? (string)$store->getCurrentCurrencyCode() : '';
    }

    private function json(mixed $value): ?string
    {
        $json = json_encode($value, self::JSON_FLAGS);

        return $json === false ? null : $json;
    }
}
