<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Console\Command;

use Faccelerate\Connect\Model\Config;
use Faccelerate\Connect\Model\Connection\ConnectService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento faccelerate:status — asks Faccelerate for the connection state and
 * saves the workspace's CRO key once the store is connected.
 */
class StatusCommand extends Command
{
    public function __construct(
        private readonly ConnectService $connectService,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('faccelerate:status')
            ->setDescription('Show and refresh the Faccelerate connection of this store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $state = $this->connectService->refresh();
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not reach Faccelerate: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('API:       ' . $this->config->getApiBaseUrl());
        $output->writeln('Status:    ' . $state['status']);
        $output->writeln('Workspace: ' . ($state['workspace_name'] ?? '-'));
        return Command::SUCCESS;
    }
}
