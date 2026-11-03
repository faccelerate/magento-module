<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Quote;

/**
 * Builds the GA4 ecommerce parameters for view_item, add_to_cart and begin_checkout, in the shape
 * gtag('event', name, params) expects: currency, value and items.
 */
class FunnelPayload
{
    /**
     * @return array<string, mixed>
     */
    public function forProduct(ProductInterface $product, float $price, string $currency): array
    {
        $price = round($price, 2);

        return [
            'currency' => $currency,
            'value' => $price,
            'items' => [[
                'item_id' => (string)$product->getSku(),
                'item_name' => (string)$product->getName(),
                'price' => $price,
                'quantity' => 1,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>|null Null for an empty cart.
     */
    public function forCart(Quote $quote): ?array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (string)$item->getSku(),
                'item_name' => (string)$item->getName(),
                'price' => round((float)$item->getPrice(), 2),
                'quantity' => $this->quantity($item->getQty()),
            ];
        }
        if ($items === []) {
            return null;
        }

        return [
            'currency' => (string)$quote->getData('quote_currency_code'),
            'value' => round((float)$quote->getData('subtotal'), 2),
            'items' => $items,
        ];
    }

    private function quantity(mixed $value): int|float
    {
        $quantity = (float)$value;

        return floor($quantity) === $quantity ? (int)$quantity : $quantity;
    }
}
