<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Shared bits of the three Connection actions. Not an action itself (no HTTP method interface).
 */
abstract class AbstractConnection extends Action
{
    public const ADMIN_RESOURCE = 'Faccelerate_Connect::config';

    protected function configRedirect(): Redirect
    {
        return $this->resultRedirectFactory->create()
            ->setPath('adminhtml/system_config/edit', ['section' => 'faccelerate']);
    }

    /**
     * The config page the Faccelerate app sends the admin user back to.
     */
    protected function configUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'faccelerate']);
    }
}
