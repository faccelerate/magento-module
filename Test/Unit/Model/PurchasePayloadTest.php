<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model;

use Faccelerate\Connect\Model\PurchasePayload;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class PurchasePayloadTest extends TestCase
{
    /**
     * @param Item[] $items
     */
    private function order(string $incrementId, array $items = []): Order&Stub
    {
        $order = $this->createStub(Order::class);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getGrandTotal')->willReturn('123.456');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getTaxAmount')->willReturn('21.4');
        $order->method('getShippingAmount')->willReturn(4.95);
        $order->method('getAllVisibleItems')->willReturn($items);

        return $order;
    }

    private function item(string $sku, string $name, mixed $price, mixed $qty): Item&Stub
    {
        $item = $this->createStub(Item::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getName')->willReturn($name);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQtyOrdered')->willReturn($qty);

        return $item;
    }

    public function testBuildsTheGa4PurchaseEvent(): void
    {
        $payload = (new PurchasePayload())->build($this->order('000000123', [
            $this->item('SHOE-42', 'Brogue "Classic"', '59.99', '2.0000'),
            $this->item('SOCK-1', 'Socks', 5, '1'),
        ]));

        self::assertSame([
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => '000000123',
                'value' => 123.46,
                'currency' => 'EUR',
                'tax' => 21.4,
                'shipping' => 4.95,
                'items' => [
                    ['item_id' => 'SHOE-42', 'item_name' => 'Brogue "Classic"', 'price' => 59.99, 'quantity' => 2],
                    ['item_id' => 'SOCK-1', 'item_name' => 'Socks', 'price' => 5.0, 'quantity' => 1],
                ],
            ],
        ], $payload);
    }

    public function testFractionalQuantityIsKept(): void
    {
        $payload = (new PurchasePayload())->build($this->order('1', [$this->item('FABRIC', 'Fabric', 10, '1.5')]));

        self::assertSame(1.5, $payload['ecommerce']['items'][0]['quantity']);
    }

    public function testOrderWithoutItemsHasAnEmptyItemList(): void
    {
        $payload = (new PurchasePayload())->build($this->order('1'));

        self::assertSame([], $payload['ecommerce']['items']);
    }

    public function testNoPayloadForAnOrderThatIsNotPlaced(): void
    {
        self::assertNull((new PurchasePayload())->build($this->order('')));
    }
}
