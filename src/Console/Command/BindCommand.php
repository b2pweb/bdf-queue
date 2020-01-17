<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Connection\AmqpLib\AmqpLibConnection;
use Bdf\Queue\Connection\Factory\ConnectionDriverFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * BindCommand
 */
class BindCommand extends Command
{
    protected static $defaultName = 'queue:bind';

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
            ->setDescription('Bind a channel or pattern to a topic.')
            ->addArgument('connection', InputArgument::REQUIRED, 'The name of connection')
            ->addArgument('topic', InputArgument::REQUIRED, 'The aiming topic.')
            ->addArgument('channels', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of channel to bind.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->connectionFactory->create($input->getArgument('connection'));

        if (!$connection instanceof AmqpLibConnection) {
            $output->writeln(sprintf('<error>The connection "%s" does not manage binding route</error>', $input->getArgument('connection')));
            return 1;
        }

        $connection->bind($input->getArgument('topic'), $input->getArgument('channels'));

        $output->writeln(sprintf(
            'Channels <info>%s</info> have been binded to topic <info>%s</info>',
            implode(', ', $input->getArgument('channels')),
            $input->getArgument('topic')
        ));

        return 0;
    }
}
