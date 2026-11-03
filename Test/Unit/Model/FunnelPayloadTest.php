<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Model;

use Faccelerate\Connect\Model\FunnelPayload;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;

class FunnelPayloadTest extends TestCase
{
    private function item(string $sku, string $name, mixed $price, mixed $qty): Item
    {
        $item = $this->createStub(Item::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getName')->willReturn($name);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQty')->willReturn($qty);

        return $item;
    }

    /**
     * @param Item[] $items
     */
    private function quote(array $items): Quote
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn($items);
        $quote->method('getData')->willReturnMap([['quote_currency_code', null, 'USD'], ['subtotal', null, '104.499']]);

        return $quote;
    }

    public function testBuildsViewItemForAProduct(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('24-MB01');
        $product->method('getName')->willReturn('Joust Duffle Bag');

        self::assertSame([
            'currency' => 'USD',
            'value' => 34.0,
            'items' => [['item_id' => '24-MB01', 'item_name' => 'Joust Duffle Bag', 'price' => 34.0, 'quantity' => 1]],
        ], (new FunnelPayload())->forProduct($product, 34.004, 'USD'));
    }

    public function testBuildsBeginCheckoutForACart(): void
    {
        $payload = (new FunnelPayload())->forCart($this->quote([
            $this->item('24-MB01', 'Joust Duffle Bag', '34.0000', '2.0000'),
            $this->item('WS12', 'Radiant Tee', 22, '1.5'),
        ]));

        self::assertSame([
            'currency' => 'USD',
            'value' => 104.5,
            'items' => [
                ['item_id' => '24-MB01', 'item_name' => 'Joust Duffle Bag', 'price' => 34.0, 'quantity' => 2],
                ['item_id' => 'WS12', 'item_name' => 'Radiant Tee', 'price' => 22.0, 'quantity' => 1.5],
            ],
        ], $payload);
    }

    public function testAnEmptyCartSendsNothing(): void
    {
        self::assertNull((new FunnelPayload())->forCart($this->quote([])));
    }
}
