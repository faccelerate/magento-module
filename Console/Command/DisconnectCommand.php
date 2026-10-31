<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Console\Command;

use Faccelerate\Connect\Model\Connection\ConnectService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento faccelerate:disconnect — the same as the Disconnect button.
 */
class DisconnectCommand extends Command
{
    public function __construct(private readonly ConnectService $connectService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('faccelerate:disconnect')
            ->setDescription('Disconnect this store from Faccelerate and delete its integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->connectService->disconnect();
        $output->writeln('Disconnected. The Faccelerate integration and its token are deleted.');
        return Command::SUCCESS;
    }
}
