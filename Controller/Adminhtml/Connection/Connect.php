<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Controller\Adminhtml\Connection;

use Faccelerate\Connect\Controller\Adminhtml\AbstractConnection;
use Faccelerate\Connect\Model\Connection\ConnectService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * The Connect button: provisions the integration, registers the store and sends the admin user's browser
 * to the Faccelerate app to pick a workspace. POST only, so Magento checks the form key.
 */
class Connect extends AbstractConnection implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly ConnectService $connectService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        try {
            $connectUrl = $this->connectService->connect($this->configUrl());

            return $this->resultRedirectFactory->create()->setUrl($connectUrl);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Faccelerate: connect failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                (string)__('Connecting to Faccelerate failed. See var/log/system.log for details.')
            );
        }

        return $this->configRedirect();
    }
}
