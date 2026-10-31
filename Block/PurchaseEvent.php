<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Block;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\PurchasePayload;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Pushes the purchase event on the order success page, once per order.
 */
class PurchaseEvent extends Template
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly PurchasePayload $purchasePayload,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The inline script, or an empty string when nothing should be pushed. The
     * event is only useful (and only safe against double counting next to a
     * shop's own GA4 setup) once the shop is connected and has a CRO key.
     */
    public function getScriptHtml(): string
    {
        if (!$this->config->isEnabled()
            || !$this->config->purchaseEventEnabled()
            || $this->config->getCroKey() === ''
        ) {
            return '';
        }

        $payload = $this->purchasePayload->build($this->checkoutSession->getLastRealOrder());
        if ($payload === null) {
            return '';
        }

        $json = json_encode($payload, self::JSON_FLAGS);
        if ($json === false) {
            return '';
        }

        $js = <<<JS
(function () {
    var event = {$json};
    var key = 'fx_purchase_' + event.ecommerce.transaction_id;
    try {
        if (window.sessionStorage.getItem(key)) {
            return;
        }
        window.sessionStorage.setItem(key, '1');
    } catch (e) {
        /* storage blocked: push anyway */
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(event);
})();
JS;

        return $this->secureRenderer->renderTag('script', [], $js, false);
    }
}
