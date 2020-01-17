<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ForgetCommand
 */
class ForgetCommand extends Command
{
    protected static $defaultName = 'queue:failer:forget';

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
        $this
            ->setDescription('Delete a failed queue job')
            ->addArgument('id', InputArgument::REQUIRED, 'The ID of the failed job')
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->failer->forget($input->getArgument('id'))) {
            $output->writeln('<info>Failed job deleted successfully</info>');

            return 0;
        } else {
            $output->writeln('<error>No failed job matches the given ID.</error>');

            return 1;
        }
    }
}
