<?php

namespace Bdf\Queue\Console\Command\Failer;

use Bdf\Queue\Failer\FailedJobStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * ShowCommand
 */
class ShowCommand extends Command
{
    protected static $defaultName = 'queue:failer:show';

    /**
     * @var FailedJobStorageInterface
     */
    private $failer;

    /**
     * The table headers for the command.
     *
     * @var array
     */
    private $headers = ['ID', 'Connection', 'Queue', 'Job', 'Error', 'Failed At'];

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
            ->setDescription('List all of the failed queue jobs')
            ->addArgument('id', InputArgument::OPTIONAL, 'The ID of the failed job')
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($id = $input->getArgument('id')) {
            $this->showOne($id, $output);
        } else {
            $this->showList($output);
        }

        return 0;
    }

    /**
     * @param string $id
     */
    protected function showOne($id, OutputInterface $output)
    {
        $job = $this->failer->find($id);

        if ($job === null) {
            $output->writeln(sprintf('<error>No failed job "%s"</error>', $id));
            return;
        }
        $failedAt = $job->failedAt ? $job->failedAt->format('H:i:s d/m/Y') : null;

        $output->writeln(<<<EOF
id.............. {$job->id}
name............ {$job->name}
connection...... {$job->connection}
queue........... {$job->queue}
error........... {$job->error}
failed at....... {$failedAt}
message class... {$job->messageClass}

<comment>message content:</comment>
EOF
        );

        if (!class_exists(CliDumper::class)) {
            var_dump($job->messageContent);
            return;
        }

        $cloner = new VarCloner();
        $cloner->addCasters([\DateTimeInterface::class => function(\DateTimeInterface $date, array $a, Stub $stub) {
            $stub->class = get_class($date);

            return ['date' => $date->format('H:i:s d/m/Y')];
        }]);

        $dumper = new CliDumper();
        $dumper->dump($cloner->cloneVar($job->messageContent), function($line, $depth) use ($output) {
            // A negative depth means "end of dump"
            if ($depth >= 0) {
                // Adds a two spaces indentation to the line
                $output->writeln(str_repeat('  ', $depth).$line);
            }
        });
    }

    /**
     *
     */
    protected function showList(OutputInterface $output)
    {
        $rows = [];

        foreach ($this->failer->all() as $job) {
            $rows[] = [
                'id' => $job->id,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'name' => $job->name,
                'error' => $job->error,
                'failedAt' => $job->failedAt ? $job->failedAt->format('H:i:s d/m/Y') : '' ,
            ];
        }

        if (count($rows) === 0) {
            $output->writeln('<info>No failed jobs found</info>');
            return;
        }

        (new Table($output))
            ->setHeaders($this->headers)
            ->setRows($rows)
            ->setStyle('box')
            ->render();
    }
}
