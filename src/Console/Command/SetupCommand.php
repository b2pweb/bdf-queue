<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Console\Command\Extension\DestinationExtension;
use Bdf\Queue\Destination\DestinationInterface;
use Bdf\Queue\Destination\DestinationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SetupCommand
 */
#[AsCommand('queue:setup', 'Declare or delete a queue from a connection.')]
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
        $this->configureDestinationOptions($this->getDefinition(), false);

        $this
            ->setDescription('Declare or delete a queue from a connection.')
            ->addOption('drop', null, InputOption::VALUE_NONE, 'Delete the queue from connection.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Apply on all declared destinations.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDrop = $input->getOption('drop');

        foreach ($this->destinations($input) as $name => $destination) {
            if ($isDrop) {
                $destination->destroy();

                $output->writeln(sprintf('The destination "<info>%s</info>" has been deleted.', $name));
            } else {
                $destination->declare();

                $output->writeln(sprintf('The destination "<info>%s</info>" has been declared.', $name));
            }
        }

        return self::SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        $this->createAutocomplete($this->manager, $input, $suggestions);
    }

    /**
     * @return iterable<string, DestinationInterface>
     */
    private function destinations(InputInterface $input): iterable
    {
        if (!$input->getOption('all')) {
            yield $input->getArgument('connection') => $this->createDestination($this->manager, $input);
            return;
        }

        foreach ($this->manager->destinationNames() as $name) {
            yield $name => $this->manager->create($name);
        }
    }
}
