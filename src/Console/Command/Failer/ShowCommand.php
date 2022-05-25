<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJob;
use Bdf\Queue\Failer\FailedJobCriteria;
use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * ShowCommand
 */
class ShowCommand extends AbstractFailerCommand
{
    protected static $defaultName = 'queue:failer:show';

    /**
     * The table headers for the command.
     *
     * @var array
     */
    private $headers = ['ID', 'Connection', 'Queue', 'Job', 'Error', 'Attempts', 'Failed At', 'First failed at'];

    /**
     * SetupCommand constructor.
     *
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

        $this->setDescription('List all of the failed queue jobs');
    }

    /**
     * {@inheritdoc}
     */
    protected function handleOne(InputInterface $input, OutputInterface $output, ?FailedJob $job): int
    {
        if ($job === null) {
            $output->writeln(sprintf('<error>No failed job "%s"</error>', $input->getArgument('id')));
            return 1;
        }

        $failedAt = $job->failedAt ? $job->failedAt->format('H:i:s d/m/Y') : null;
        $firstFailedAt = $job->firstFailedAt ? $job->firstFailedAt->format('H:i:s d/m/Y') : null;

        $output->writeln(
            <<<EOF
id.............. {$job->id}
name............ {$job->name}
connection...... {$job->connection}
queue........... {$job->queue}
error........... {$job->error}
attempts........ {$job->attempts}
failed at....... {$failedAt}
first failed at. {$firstFailedAt}
message class... {$job->messageClass}

<comment>message content:</comment>
EOF
        );

        if (!class_exists(CliDumper::class)) {
            var_dump($job->messageContent);
            return 0;
        }

        $cloner = new VarCloner();
        $cloner->addCasters([\DateTimeInterface::class => function (\DateTimeInterface $date, array $a, Stub $stub) {
            $stub->class = get_class($date);

            return ['date' => $date->format('H:i:s d/m/Y')];
        }]);

        $dumper = new CliDumper();
        $dumper->dump($cloner->cloneVar($job->messageContent), function ($line, $depth) use ($output) {
            // A negative depth means "end of dump"
            if ($depth >= 0) {
                // Adds a two spaces indentation to the line
                $output->writeln(str_repeat('  ', $depth).$line);
            }
        });

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleCriteria(InputInterface $input, OutputInterface $output, FailedJobCriteria $criteria): int
    {
        $rows = [];

        foreach ($this->repository->search($criteria) as $job) {
            $rows[] = [
                'id' => $job->id,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'name' => $job->name,
                'error' => $job->error,
                'attempts' => $job->attempts,
                'failedAt' => $job->failedAt ? $job->failedAt->format('H:i:s d/m/Y') : '' ,
                'firstFailedAt' => $job->firstFailedAt ? $job->firstFailedAt->format('H:i:s d/m/Y') : '' ,
            ];
        }

        if (count($rows) === 0) {
            $output->writeln('<info>No failed jobs found</info>');
            return 0;
        }

        (new Table($output))
            ->setHeaders($this->headers)
            ->setRows($rows)
            ->setStyle('box')
            ->render();

        return 0;
    }
}
