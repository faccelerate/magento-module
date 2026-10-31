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
 * The Disconnect button. POST only, so Magento checks the form key.
 */
class Disconnect extends AbstractConnection implements HttpPostActionInterface
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
            $this->connectService->disconnect();
            $this->messageManager->addSuccessMessage((string)__('Disconnected from Faccelerate.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Faccelerate: disconnect failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                (string)__('Disconnecting from Faccelerate failed. See var/log/system.log for details.')
            );
        }

        return $this->configRedirect();
    }
}
