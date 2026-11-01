<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Block;

use Faccelerate\Connect\Block\PurchaseEvent;
use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\PurchasePayload;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PurchaseEventTest extends TestCase
{
    private function block(
        bool $enabled,
        bool $purchaseEvent,
        string $croKey,
        ?array $payload,
        SecureHtmlRenderer $secureRenderer
    ): PurchaseEvent {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('purchaseEventEnabled')->willReturn($purchaseEvent);
        $config->method('getCroKey')->willReturn($croKey);

        $session = $this->createStub(Session::class);
        $session->method('getLastRealOrder')->willReturn($this->createStub(Order::class));

        $purchasePayload = $this->createStub(PurchasePayload::class);
        $purchasePayload->method('build')->willReturn($payload);

        return new PurchaseEvent(
            $this->createStub(Context::class),
            $config,
            $session,
            $purchasePayload,
            $secureRenderer
        );
    }

    public function testRendersOnceGuardedInlineScriptThroughTheSecureRenderer(): void
    {
        $captured = null;
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects(self::once())->method('renderTag')->willReturnCallback(
            function (string $tag, array $attributes, ?string $content, bool $textContent) use (&$captured): string {
                $captured = [$tag, $attributes, $content, $textContent];

                return '<script>rendered</script>';
            }
        );
        $block = $this->block(true, true, 'fx1', [
            'event' => 'purchase',
            'ecommerce' => ['transaction_id' => '1', 'items' => [['item_name' => '</script><b>&\'"']]],
        ], $secureRenderer);

        self::assertSame('<script>rendered</script>', $block->getScriptHtml());
        [$tag, $attributes, $content, $textContent] = $captured;
        self::assertSame('script', $tag);
        self::assertSame([], $attributes);
        self::assertFalse($textContent);
        self::assertStringContainsString('sessionStorage', $content);
        self::assertStringContainsString("'fx_purchase_' + event.ecommerce.transaction_id", $content);
        self::assertStringContainsString('window.dataLayer.push(event)', $content);
        // Nothing from the order can end the script element or break out of the JSON string.
        self::assertStringNotContainsString('</script>', $content);
        self::assertStringContainsString('\\u003C\\/script\\u003E', $content);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string}>
     */
    public static function switchedOffProvider(): array
    {
        return [
            'module disabled' => [false, true, 'fx1'],
            'purchase event disabled' => [true, false, 'fx1'],
            'not connected (no key)' => [true, true, ''],
        ];
    }

    #[DataProvider('switchedOffProvider')]
    public function testNothingWhenSwitchedOffOrNotConnected(bool $enabled, bool $event, string $key): void
    {
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects(self::never())->method('renderTag');

        self::assertSame('', $this->block($enabled, $event, $key, ['event' => 'purchase'], $secureRenderer)->getScriptHtml());
    }

    public function testNothingWithoutAPlacedOrder(): void
    {
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects(self::never())->method('renderTag');

        self::assertSame('', $this->block(true, true, 'fx1', null, $secureRenderer)->getScriptHtml());
    }
}
