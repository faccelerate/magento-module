<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Block\Adminhtml\Connection;

use Faccelerate\Connect\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;

/**
 * The confirm page for one connection action: what it does, and a form that posts to it.
 */
class Confirm extends Template
{
    public const ACTIONS = ['connect', 'refresh', 'disconnect'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAction(): string
    {
        $action = (string)$this->getRequest()->getParam('do');

        return in_array($action, self::ACTIONS, true) ? $action : 'refresh';
    }

    public function getHeading(): Phrase
    {
        return match ($this->getAction()) {
            'connect' => __('Connect this store to Faccelerate'),
            'disconnect' => __('Disconnect this store from Faccelerate'),
            default => __('Check the Faccelerate connection'),
        };
    }

    public function getExplanation(): Phrase
    {
        return match ($this->getAction()) {
            'connect' => __(
                'Magento creates an integration named "Faccelerate" with read access to store settings, products, '
                . 'categories, orders and CMS pages, and write access Faccelerate only uses for texts you approve. '
                . 'Then you sign in to Faccelerate (%1) and pick the workspace for this store.',
                $this->config->getApiBaseUrl()
            ),
            'disconnect' => __(
                'Faccelerate stops reading this store and deletes its keys, and Magento deletes the "Faccelerate" '
                . 'integration. The CRO script disappears from the storefront.'
            ),
            default => __('Asks Faccelerate whether the store is connected, and to which workspace.'),
        };
    }

    public function getButtonLabel(): Phrase
    {
        return match ($this->getAction()) {
            'connect' => __('Connect'),
            'disconnect' => __('Disconnect'),
            default => __('Check status'),
        };
    }

    public function isDestructive(): bool
    {
        return $this->getAction() === 'disconnect';
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('faccelerate/connection/' . $this->getAction());
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'faccelerate']);
    }
}
