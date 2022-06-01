<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobRepositoryAdapter;
use Bdf\Queue\Failer\FailedJobRepositoryInterface;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class for perform operation on failed jobs
 */
abstract class AbstractFailerCommand extends Command
{
    /**
     * @var FailedJobRepositoryInterface
     * @read-only
     */
    protected $repository;

    /**
     * AbstractFailerCommand constructor.
     *
     * @param FailedJobStorageInterface $storage
     */
    public function __construct(FailedJobStorageInterface $storage)
    {
        parent::__construct(static::$defaultName);

        $this->repository = FailedJobRepositoryAdapter::adapt($storage);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'The ID of the failed job')
            ->addOption('name', null, InputArgument::OPTIONAL, 'Filter by the message name. Wildcard can be used.')
            ->addOption('connection', null, InputArgument::OPTIONAL, 'Filter by the queue connection name.')
            ->addOption('queue', null, InputArgument::OPTIONAL, 'Filter by the queue name.')
            ->addOption('error', null, InputArgument::OPTIONAL, 'Search for error message. Wildcard can be used.')
            ->addOption('failedAt', null, InputArgument::OPTIONAL, 'Filter by failing date. You can prefix the date with an operator like > or <.')
            ->addOption('firstFailedAt', null, InputArgument::OPTIONAL, 'Filter by original failing date. You can prefix the date with an operator like > or <.')
            ->addOption('attempts', null, InputArgument::OPTIONAL, 'Filter by number of retry attempts. You can prefix the number with an operator like > or <.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($id = $input->getArgument('id')) {
            return $this->handleOne($input, $output, $this->repository->findById($id));
        } else {
            return $this->handleCriteria($input, $output, $this->buildCriteria($input));
        }
    }

    /**
     * Handle job, when "id" argument is used
     *
     * @param InputInterface $input The CLI input
     * @param OutputInterface $output The CLI output
     * @param FailedJob|null $job The found job. Null is not found
     *
     * @return int The command result. 0 for success.
     */
    abstract protected function handleOne(InputInterface $input, OutputInterface $output, ?FailedJob $job): int;

    /**
     * Handle bulk operation, using a criteria
     *
     * @param InputInterface $input The CLI input
     * @param OutputInterface $output The CLI output
     * @param FailedJobCriteria $criteria The filter criteria
     *
     * @return int The command result. 0 for success.
     */
    abstract protected function handleCriteria(InputInterface $input, OutputInterface $output, FailedJobCriteria $criteria): int;

    /**
     * Build the criteria from CLI options
     *
     * @param InputInterface $input
     *
     * @return FailedJobCriteria
     */
    protected function buildCriteria(InputInterface $input): FailedJobCriteria
    {
        $criteria = new FailedJobCriteria();

        if ($name = $input->getOption('name')) {
            $criteria->name($name);
        }

        if ($connection = $input->getOption('connection')) {
            $criteria->connection($connection);
        }

        if ($queue = $input->getOption('queue')) {
            $criteria->queue($queue);
        }

        if ($error = $input->getOption('error')) {
            $criteria->error($error);
        }

        if ($failedAt = $input->getOption('failedAt')) {
            $criteria->failedAt($failedAt);
        }

        if ($firstFailedAt = $input->getOption('firstFailedAt')) {
            $criteria->firstFailedAt($firstFailedAt);
        }

        if ($attempts = $input->getOption('attempts')) {
            $criteria->attempts($attempts);
        }

        return $criteria;
    }
}
