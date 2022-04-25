<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Connection\ConnectionDriverInterface;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 
 */
class InfoCommand extends Command
{
    protected static $defaultName = 'queue:info';

    /**
     * @var ConnectionDriverFactoryInterface
     */
    private $connectionFactory;

    /**
     * SetupCommand constructor.
     *
     * @param ConnectionDriverFactoryInterface $connectionFactory
     */
    public function __construct(ConnectionDriverFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;

        parent::__construct(static::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Display queue info')
            ->addArgument('connection', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Queue connections.')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'This will display only the report filtered by its name. The list of reports depends of each driver.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = $input->getOption('filter');
        $connections = array_map([$this->connectionFactory, 'create'], $input->getArgument('connection'));

        /** @var ConnectionDriverInterface $connection */
        foreach ($connections as $connection) {
            $output->writeln(sprintf('<comment>Server: %s</comment>', $connection->getName()));
            $reports = $connection->queue()->stats();

            if (empty($reports)) {
                $output->writeln('Reports are not available for this connection.');
                continue;
            }

            foreach ($reports as $report => $stats) {
                if ($filter !== null && $filter !== $report) {
                    continue;
                }

                $output->writeln(sprintf('------ Report: <comment>%s</comment>', $report));

                if (empty($stats)) {
                    $output->writeln('No result found');
                } else {
                    (new Table($output))
                        ->setHeaders(isset($stats[0]) ? array_keys($stats[0]) : [])
                        ->setRows($stats)
                        ->setStyle('box')
                        ->render();
                }
            }
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('connection')) {
            $suggestions->suggestValues($this->connectionFactory->connectionNames());
        }

        if ($input->mustSuggestOptionValuesFor('filter')) {
            $suggestions->suggestValues(['queues', 'workers']);
        }
    }
}
