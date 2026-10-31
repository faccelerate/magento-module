<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Controller\Adminhtml\Connection;

use Faccelerate\Connect\Controller\Adminhtml\AbstractConnection;
use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Connection\ConnectService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * The "Check status" button.
 */
class Refresh extends AbstractConnection implements HttpPostActionInterface
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
            $state = $this->connectService->refresh();
            if ($state['status'] === Config::STATUS_CONNECTED) {
                $this->messageManager->addSuccessMessage(
                    (string)__('Connected to Faccelerate workspace "%1".', (string)$state['workspace_name'])
                );
            } elseif ($state['status'] === Config::STATUS_PENDING) {
                $this->messageManager->addNoticeMessage(
                    (string)__('Faccelerate is still waiting for you to pick a workspace.')
                );
            } else {
                $this->messageManager->addNoticeMessage((string)__('This shop is not connected to Faccelerate.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Faccelerate: status check failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                (string)__('Checking the Faccelerate status failed. See var/log/system.log for details.')
            );
        }

        return $this->configRedirect();
    }
}
