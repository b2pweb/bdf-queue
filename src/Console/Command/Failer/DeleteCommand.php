<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for delete failed jobs
 */
#[AsCommand('queue:failer:delete', 'Delete failed queue jobs')]
class DeleteCommand extends AbstractFailerCommand
{
    protected static $defaultName = 'queue:failer:delete';

    /**
     * @param FailedJobStorageInterface $storage
     */
    public function __construct(FailedJobStorageInterface $storage)
    {
        parent::__construct($storage);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Delete failed queue jobs');
    }

    /**
     * {@inheritdoc}
     */
    protected function handleOne(InputInterface $input, OutputInterface $output, ?FailedJob $job): int
    {
        if (!$job || !$this->repository->delete($job)) {
            $output->writeln('<error>No failed job matches the given ID.</error>');

            return 1;
        }

        $output->writeln('<info>Failed job deleted successfully</info>');

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleCriteria(InputInterface $input, OutputInterface $output, FailedJobCriteria $criteria): int
    {
        $count = $this->repository->purge($criteria);

        $output->writeln(sprintf('<info>%d failed jobs deleted successfully</info>', $count));

        return 0;
    }
}
