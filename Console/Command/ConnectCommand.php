<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Console\Command;

use Faccelerate\Connect\Model\Connection\ConnectService;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento faccelerate:connect — the same as the Connect button: creates the
 * integration, registers the store and prints the link where you pick a workspace.
 */
class ConnectCommand extends Command
{
    public function __construct(
        private readonly ConnectService $connectService,
        private readonly BackendUrl $backendUrl,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('faccelerate:connect')
            ->setDescription('Connect this store to Faccelerate and print the link to pick a workspace');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area already set.
        }
        $this->backendUrl->turnOffSecretKey();
        $returnUrl = $this->backendUrl->getUrl('adminhtml/system_config/edit', ['section' => 'faccelerate']);

        try {
            $connectUrl = $this->connectService->connect($returnUrl);
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not connect: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('Open this link, sign in to Faccelerate and pick the workspace for this store:');
        $output->writeln($connectUrl);
        $output->writeln('Then run bin/magento faccelerate:status to finish.');
        return Command::SUCCESS;
    }
}
