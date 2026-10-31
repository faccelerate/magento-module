<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Setup;

use Faccelerate\Connect\Model\Connection\ConnectService;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Psr\Log\LoggerInterface;

/**
 * bin/magento module:uninstall --remove-data: tell Faccelerate (best effort), delete the integration and
 * remove every faccelerate/* setting.
 */
class Uninstall implements UninstallInterface
{
    public function __construct(
        private readonly ConnectService $connectService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $setup->startSetup();

        try {
            $this->connectService->disconnect();
        } catch (\Throwable $e) {
            $this->logger->warning('Faccelerate: disconnect during uninstall failed: ' . $e->getMessage());
        }

        $setup->getConnection()->delete(
            $setup->getTable('core_config_data'),
            ['path LIKE ?' => 'faccelerate/%']
        );

        $setup->endSetup();
    }
}
