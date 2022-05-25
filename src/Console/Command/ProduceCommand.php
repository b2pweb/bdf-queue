<?php

namespace Bdf\Queue\Console\Command;

use Bdf\Queue\Console\Command\Extension\DestinationExtension;
use Bdf\Queue\Destination\DestinationManager;
use Bdf\Queue\Message\Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Produce a serialized message onto a given queue
 */
class ProduceCommand extends Command
{
    use DestinationExtension;

    protected static $defaultName = 'queue:produce';

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
            ->setDescription('Push a serialized message onto queue')
            ->addArgument('message', InputArgument::REQUIRED, 'The serialized message to push.')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Amount of time to delay message.', 0)
            ->addOption('payload', null, InputOption::VALUE_NONE, 'Send message as raw payload.')
            ->setHelp(
                <<<EOF
The <info>%command.name%</info> command push a serialized message onto a given queue from a connection.
The message will be push as raw value. The serialization should respect the connection parameter.

Exemple
  <info>php %command.full_name% connection '{"data":"foo"}'</info>
  <info>php %command.full_name% connection foo --payload'</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputMessage = 'Message has been sent.';
        $destination = $this->createDestination($this->manager, $input);

        if (!$input->getOption('payload')) {
            $destination->raw($input->getArgument('message'), ['delay' => $input->getOption('delay')]);
        } else {
            if ($input->getOption('topic')) {
                $outputMessage = 'Message has been published in topic.';
                $message = Message::createForTopic($input->getOption('topic'), $input->getArgument('message'));
            } else {
                $outputMessage = 'Message has been sent in queue.';
                $message = Message::create($input->getArgument('message'), $input->getOption('queue'), $input->getOption('delay'));
            }

            $message->setDestination($input->getArgument('connection'));

            $destination->send($message);
        }

        $output->writeln($outputMessage);

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
