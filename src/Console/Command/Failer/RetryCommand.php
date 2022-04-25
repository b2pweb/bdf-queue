<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RetryCommand
 */
class RetryCommand extends AbstractFailerCommand
{
    protected static $defaultName = 'queue:failer:retry';

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * SetupCommand constructor.
     *
     * @param FailedJobStorageInterface $storage
     * @param DestinationManager $manager
     */
    public function __construct(FailedJobStorageInterface $storage, DestinationManager $manager)
    {
        parent::__construct($storage);

        $this->manager = $manager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Retry a failed queue job');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('id') === 'all') {
            return $this->handleAll($output);
        }

        return parent::execute($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleOne(InputInterface $input, OutputInterface $output, ?FailedJob $job): int
    {
        if (!$job) {
            $output->writeln('<error>No failed job matches the given ID.</error>');
            return 1;
        }

        $this->doRetry($job, $output);

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleCriteria(InputInterface $input, OutputInterface $output, FailedJobCriteria $criteria): int
    {
        foreach ($this->repository->search($criteria) as $job) {
            $this->doRetry($job, $output);
        }

        return 0;
    }

    /**
     * Retry all failed jobs (without any filters)
     */
    protected function handleAll(OutputInterface $output): int
    {
        foreach ($this->repository->all() as $job) {
            $this->doRetry($job, $output);
        }

        return 0;
    }

    /**
     * Repush the job into queue
     *
     * @param FailedJob $job
     * @param OutputInterface $output
     *
     * @return void
     */
    private function doRetry(FailedJob $job, OutputInterface $output): void
    {
        $job->attempts++;

        if ($message = $job->toMessage()) {
            $this->manager->send($message);
        }

        $this->repository->delete($job);

        $output->writeln(sprintf('Job <info>#%s</info> has been pushed back onto the queue', $job->id));
    }
}
