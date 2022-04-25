<?php

namespace Bdf\Queue\Console\Command\Extension;

use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Destination\DestinationInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base command for handle destinations
 */
trait DestinationExtension
{
    /**
     * Creates the destination
     *
     * @param DestinationManager $destinationManager
     * @param InputInterface $input
     *
     * @return DestinationInterface
     */
    public function createDestination(DestinationManager $destinationManager, InputInterface $input): DestinationInterface
    {
        $connectionName = $input->getArgument('connection');

        if ($queue = $input->getOption('queue')) {
            return $destinationManager->queue($connectionName, strpos($queue, ',') === false ? $queue : explode(',', $queue));
        }

        if ($topic = $input->getOption('topic')) {
            return $destinationManager->topic($connectionName, $topic);
        }

        return $destinationManager->guess($connectionName);
    }

    /**
     * Configure the destination arguments and options
     *
     * @param InputDefinition $definition
     */
    public function configureDestinationOptions(InputDefinition $definition)
    {
        $definition->addArgument(new InputArgument('connection', InputArgument::REQUIRED, 'The name of connection'));
        $definition->addOption(new InputOption('queue', null, InputOption::VALUE_REQUIRED, 'The queues to listen on. can be separated by comma (only for reading).'));
        $definition->addOption(new InputOption('topic', null, InputOption::VALUE_REQUIRED, 'The topic to subscribe.'));
    }

    /**
     * Create the autocompletion for the argument connection
     *
     * @param DestinationManager $destinationManager
     * @param CompletionInput $input
     * @param CompletionSuggestions $suggestions
     *
     * @return void
     */
    public function createAutocomplete(DestinationManager $destinationManager, CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('connection')) {
            $suggestions->suggestValues($destinationManager->destinationNames());
            $suggestions->suggestValues($destinationManager->connectionNames());
        }
    }
}
