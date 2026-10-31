<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Test\Unit\Block;

use Faccelerate\Connect\Block\Loader;
use Faccelerate\Connect\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class LoaderTest extends TestCase
{
    private Config&Stub $config;
    private Http&Stub $request;
    private Loader $block;

    protected function setUp(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->request = $this->createStub(Http::class);
        $this->block = $this->blockWith($this->createStub(SecureHtmlRenderer::class));
    }

    private function blockWith(SecureHtmlRenderer $secureRenderer): Loader
    {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getScopeConfig')->willReturn($this->createStub(ScopeConfigInterface::class));
        $context->method('getEventManager')->willReturn($this->createStub(ManagerInterface::class));

        return new Loader($context, $this->config, $secureRenderer);
    }

    private function givenShop(bool $enabled, string $croKey, bool $loadOnCheckout, string $action): void
    {
        $this->config->method('isEnabled')->willReturn($enabled);
        $this->config->method('getCroKey')->willReturn($croKey);
        $this->config->method('loadOnCheckout')->willReturn($loadOnCheckout);
        $this->config->method('getApiBaseUrl')->willReturn('https://api.faccelerate.ai');
        $this->request->method('getFullActionName')->willReturn($action);
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: bool, 3: string, 4: bool}>
     */
    public static function renderProvider(): array
    {
        return [
            'enabled with key on home' => [true, 'fx1', true, 'cms_index_index', true],
            'disabled' => [false, 'fx1', true, 'cms_index_index', false],
            'no key' => [true, '', true, 'cms_index_index', false],
            'checkout, load on checkout on' => [true, 'fx1', true, 'checkout_index_index', true],
            'checkout, load on checkout off' => [true, 'fx1', false, 'checkout_index_index', false],
            'checkout failure page, off' => [true, 'fx1', false, 'checkout_onepage_failure', false],
            'multishipping, off' => [true, 'fx1', false, 'multishipping_checkout_shipping', false],
            'multishipping, on' => [true, 'fx1', true, 'multishipping_checkout_shipping', true],
            'cart, off' => [true, 'fx1', false, 'checkout_cart_index', true],
            'success page, off' => [true, 'fx1', false, 'checkout_onepage_success', true],
            'category, off' => [true, 'fx1', false, 'catalog_category_view', true],
            'customer account, off' => [true, 'fx1', false, 'customer_account_index', true],
            'name that merely contains checkout, off' => [true, 'fx1', false, 'cms_checkout_info', true],
            'no action name' => [true, 'fx1', false, '', true],
        ];
    }

    #[DataProvider('renderProvider')]
    public function testShouldRender(bool $enabled, string $key, bool $onCheckout, string $action, bool $expected): void
    {
        $this->givenShop($enabled, $key, $onCheckout, $action);

        self::assertSame($expected, $this->block->shouldRender());
    }

    public function testLoaderUrlUsesTheApiBase(): void
    {
        $this->givenShop(true, 'fx1', true, 'cms_index_index');

        self::assertSame('https://api.faccelerate.ai/x/cro/loader.js', $this->block->getLoaderUrl());
    }

    public function testScriptTagGoesThroughTheSecureRenderer(): void
    {
        $this->givenShop(true, 'fxKey', true, 'cms_index_index');
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects(self::once())->method('renderTag')->with(
            'script',
            [
                'async' => '',
                'src' => 'https://api.faccelerate.ai/x/cro/loader.js',
                'data-fx' => 'fxKey',
            ]
        )->willReturn('<script async="" src="https://api.faccelerate.ai/x/cro/loader.js" data-fx="fxKey"></script>');

        self::assertStringContainsString('data-fx="fxKey"', $this->blockWith($secureRenderer)->getScriptHtml());
    }

    public function testToHtmlIsEmptyWhenNothingShouldRender(): void
    {
        $this->givenShop(true, '', true, 'cms_index_index');
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects(self::never())->method('renderTag');

        self::assertSame('', $this->blockWith($secureRenderer)->toHtml());
    }
}
