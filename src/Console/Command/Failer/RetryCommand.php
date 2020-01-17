<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RetryCommand
 */
class RetryCommand extends Command
{
    protected static $defaultName = 'queue:failer:retry';

    /**
     * @var FailedJobStorageInterface
     */
    private $failer;

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * SetupCommand constructor.
     *
     * @param FailedJobStorageInterface $failer
     * @param DestinationManager $manager
     */
    public function __construct(FailedJobStorageInterface $failer, DestinationManager $manager)
    {
        $this->failer = $failer;
        $this->manager = $manager;

        parent::__construct(static::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Retry a failed queue job')
            ->addArgument('id', InputArgument::REQUIRED, 'The ID of the failed job. Type "all" to retry all job.')
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->getFailedJobs($input->getArgument('id'), $output) as $job) {
            if ($message = $job->toMessage()) {
                $this->manager->send($message);
            }

            $this->failer->forget($job->id);

            $output->writeln(sprintf('Job <info>#%s</info> has been pushed back onto the queue', $job->id));
        }

        return 0;
    }

    /**
     * Get the list of failed jobs
     *
     * @param string $id
     *
     * @param OutputInterface $output
     * @return FailedJob[]
     */
    private function getFailedJobs($id, OutputInterface $output)
    {
        if ($id === 'all') {
            return $this->failer->all();
        }

        $job = $this->failer->find($id);

        if ($job === null) {
            $output->writeln('<error>No failed job matches the given ID.</error>');
            return [];
        }

        return [$job];
    }
}
