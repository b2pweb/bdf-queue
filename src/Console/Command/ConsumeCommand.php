<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Console\Command\Extension\DestinationExtension;
use Bdf\Queue\Consumer\Receiver\Builder\ReceiverLoaderInterface;
use Bdf\Queue\Consumer\ReceiverInterface;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Worker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ConsumeCommand
 */
class ConsumeCommand extends Command
{
    use DestinationExtension;

    protected static $defaultName = 'queue:consume';

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * @var ReceiverLoaderInterface
     */
    protected $receivers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SetupCommand constructor.
     *
     * @param DestinationManager $manager
     * @param ReceiverLoaderInterface $receivers
     * @param LoggerInterface|null $logger
     */
    public function __construct(DestinationManager $manager, ReceiverLoaderInterface $receivers, LoggerInterface $logger = null)
    {
        $this->manager = $manager;
        $this->receivers = $receivers;
        $this->logger = $logger;

        parent::__construct(static::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->configureDestinationOptions($this->getDefinition());

        $this
            ->addOption('memory', null, InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume. You can use shorthand byte values [K, M or G]', '128M')
            ->addOption('duration', null, InputOption::VALUE_REQUIRED, 'Number of seconds to wait until a job is available', 3)
            ->addOption('retry', null, InputOption::VALUE_REQUIRED, 'Number of retries for failed jobs.', 0)
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Amount of time to delay failed jobs before retry.', 10)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of jobs in one loop.', 0)
            ->addOption('save', null, InputOption::VALUE_NONE, 'Save failed job.')
            ->addOption('max', null, InputOption::VALUE_REQUIRED, 'The max number of jobs.')
            ->addOption('stopWhenEmpty', null, InputOption::VALUE_NONE, 'Stop the worker if the queues are empty.')
            ->addOption('stopOnError', null, InputOption::VALUE_NONE, 'Stop the worker if error occurs.')
            ->addOption('logger', null, InputOption::VALUE_REQUIRED, 'The logger to use "stdout", "null", or "default".', "default")
            ->addOption('middleware', 'm', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Add a middleware by its name.')
            ->setDescription('Consume a message from a queue.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = $this->createDestination($this->manager, $input);

        $worker = new Worker($destination->consumer($this->createExtension($input, $output)));
        $worker->run(['duration' => $input->getOption('duration')]);

        return 0;
    }

    /**
     * Create the stack of receivers
     *
     * @return ReceiverInterface
     */
    protected function createExtension(InputInterface $input, OutputInterface $output)
    {
        $builder = $this->receivers->load($input->getArgument('connection'));
        $builder->log($this->getLogger($input->getOption('logger'), $output));

        if ($input->getOption('middleware')) {
            foreach ($input->getOption('middleware') as $middleware) {
                if (!$builder->exists($middleware)) {
                    $output->writeln('<error>Try to add an unknown middleware "'.$middleware.'"</error>');
                } else {
                    $builder->add($middleware);
                }
            }
        }

        if ($input->getOption('retry')) {
            $builder->retry($input->getOption('retry'), $input->getOption('delay'));
        }

        if ($input->getOption('save')) {
            $builder->store();
        }

        if ($input->getOption('limit')) {
            $builder->limit($input->getOption('limit'), $input->getOption('duration'));
        }

        // Set the no failure here because the Stop...Receiver does not manage exception
        if (!$input->getOption('stopOnError')) {
            $builder->noFailure();
        }

        if ($input->getOption('stopWhenEmpty')) {
            $builder->stopWhenEmpty();
        }

        if ($input->getOption('max') > 0) {
            $builder->max($input->getOption('max'));
        }

        $memory = $this->convertToBytes($input->getOption('memory'));
        if ($memory > 0) {
            $builder->memory($memory);
        }

        return $builder->build();
    }

    /**
     * Convert the given string value in bytes.
     *
     * @param string $value
     *
     * @return int
     */
    public function convertToBytes(string $value): int
    {
        $value = strtolower(trim($value));
        $unit = substr($value, -1);
        $bytes = (int) $value;

        switch ($unit) {
            case 't': $bytes *= 1024;
            // no break
            case 'g': $bytes *= 1024;
            // no break
            case 'm': $bytes *= 1024;
            // no break
            case 'k': $bytes *= 1024;
        }

        return $bytes;
    }

    /**
     * Get the logger for consumer.
     *
     * @param string $value
     * @param OutputInterface $output
     *
     * @return null|LoggerInterface
     */
    public function getLogger(string $value, OutputInterface $output): ?LoggerInterface
    {
        switch ($value) {
            case 'stdout':
                return new ConsoleLogger($output);

            case 'null':
                return new NullLogger();
        }

        return $this->logger;
    }
}
