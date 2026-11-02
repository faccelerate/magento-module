<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Controller\Adminhtml\Connection;

use Faccelerate\Connect\Block\Adminhtml\Connection\Confirm as ConfirmBlock;
use Faccelerate\Connect\Controller\Adminhtml\AbstractConnection;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * A small page with its own form for Connect, Check status and Disconnect. The connection panel lives inside
 * the configuration page's form, and a form inside a form is dropped by the browser, so the panel links here
 * and this page posts to the action. Shows nothing that changes state; the POST actions do the work.
 */
class Confirm extends AbstractConnection implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $action = (string)$this->getRequest()->getParam('do');
        if (!in_array($action, ConfirmBlock::ACTIONS, true)) {
            return $this->configRedirect();
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Magento_Config::system_config');
        $page->getConfig()->getTitle()->prepend((string)__('Faccelerate'));

        return $page;
    }
}
