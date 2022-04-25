<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Console\Command\Extension\DestinationExtension;
use Bdf\Queue\Destination\DestinationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SetupCommand
 */
class SetupCommand extends Command
{
    use DestinationExtension;

    protected static $defaultName = 'queue:setup';

    /**
     * @var DestinationManager
     */
    private $manager;

    /**
     * SetupCommand constructor.
     *
     * @param DestinationManager $manager
     */
    public function __construct(DestinationManager $manager)
    {
        $this->manager = $manager;

        parent::__construct(static::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->configureDestinationOptions($this->getDefinition());

        $this
            ->setDescription('Declare or delete a queue from a connection.')
            ->addOption('drop', null, InputOption::VALUE_NONE, 'Delete the queue from connection.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = $this->createDestination($this->manager, $input);

        if ($input->getOption('drop')) {
            $destination->destroy();

            $output->writeln(sprintf('The destination "<info>%s</info>" has been deleted.', $input->getArgument('connection')));
        } else {
            $destination->declare();

            $output->writeln(sprintf('The destination "<info>%s</info>" has been declared.', $input->getArgument('connection')));
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        $this->createAutocomplete($this->manager, $input, $suggestions);
    }
}
