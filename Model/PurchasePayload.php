<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model;

use Magento\Sales\Model\Order;

/**
 * Builds the GA4-style purchase event for the data layer from a placed order.
 */
class PurchasePayload
{
    /**
     * @return array<string, mixed>|null Null when the order is not a placed order.
     */
    public function build(Order $order): ?array
    {
        $transactionId = (string)$order->getIncrementId();
        if ($transactionId === '') {
            return null;
        }

        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (string)$item->getSku(),
                'item_name' => (string)$item->getName(),
                'price' => $this->amount($item->getPrice()),
                'quantity' => $this->quantity($item->getQtyOrdered()),
            ];
        }

        return [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => $transactionId,
                'value' => $this->amount($order->getGrandTotal()),
                'currency' => (string)$order->getOrderCurrencyCode(),
                'tax' => $this->amount($order->getTaxAmount()),
                'shipping' => $this->amount($order->getShippingAmount()),
                'items' => $items,
            ],
        ];
    }

    private function amount(mixed $value): float
    {
        return round((float)$value, 2);
    }

    private function quantity(mixed $value): int|float
    {
        $quantity = (float)$value;

        return floor($quantity) === $quantity ? (int)$quantity : $quantity;
    }
}
