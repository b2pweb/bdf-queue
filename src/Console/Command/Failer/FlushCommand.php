<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FlushCommand
 */
class FlushCommand extends Command
{
    protected static $defaultName = 'queue:failer:flush';

    /**
     * @var FailedJobStorageInterface
     */
    private $failer;

    /**
     * SetupCommand constructor.
     *
     * @param FailedJobStorageInterface $failer
     */
    public function __construct(FailedJobStorageInterface $failer)
    {
        $this->failer = $failer;

        parent::__construct(static::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Flush all of the failed queue jobs');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->failer->flush();

        $output->writeln('<info>All failed jobs deleted successfully</info>');

        return 0;
    }
}
