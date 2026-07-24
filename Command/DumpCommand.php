<?php
/**
 * Created by PhpStorm.
 * User: bra
 * Date: 5/08/15
 * Time: 8:58
 *
 * FORK OF ASSETIC DUMP COMMAND FOR USE MY OWN ABSTRACT COMMAND CLASS
 */

declare(strict_types=1);

namespace Xilon\GaufretteAssetsBundle\Command;

use Spork\Batch\Strategy\ChunkStrategy;
use Spork\EventDispatcher\WrappedEventDispatcher;
use Spork\ProcessManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class DumpCommand extends AbstractCommand {
    private ?ProcessManager $spork = null;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('bds:assetic:dump')
            ->setDescription('Dumps all assets to the filesystem')
            ->addArgument('write_to', InputArgument::OPTIONAL, 'Override the configured asset root')
            ->addOption('forks', null, InputOption::VALUE_REQUIRED, 'Fork work across many processes (requires kriswallsmith/spork)')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'DEPRECATED: use assetic:watch instead')
            ->addOption('force', null, InputOption::VALUE_NONE, 'DEPRECATED: use assetic:watch instead')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'DEPRECATED: use assetic:watch instead', 1)
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $stdout): void
    {
        if (null !== $input->getOption('forks')) {
            if (!class_exists('Spork\ProcessManager')) {
                throw new \RuntimeException('The --forks option requires that package kriswallsmith/spork be installed');
            }

            if (!is_numeric($input->getOption('forks'))) {
                throw new \InvalidArgumentException('The --forks options must be numeric');
            }

            $this->spork = new ProcessManager(
                new WrappedEventDispatcher($this->getContainer()->get('event_dispatcher')),
                null,
                $this->getContainer()->getParameter('kernel.debug')
            );
        }

        parent::initialize($input, $stdout);
    }

    protected function execute(InputInterface $input, OutputInterface $stdout)
    {
        // capture error output
        $stderr = $stdout instanceof ConsoleOutputInterface
            ? $stdout->getErrorOutput()
            : $stdout;

        if ($input->getOption('watch')) {
            $stderr->writeln(
                '<error>The --watch option is deprecated. Please use the '.
                'assetic:watch command instead.</error>'
            );

            // build assetic:watch arguments
            $arguments = array(
                'command'  => 'assetic:watch',
                'write_to' => $this->basePath,
                '--period' => $input->getOption('period'),
                '--env'    => $input->getOption('env'),
            );

            if ($input->getOption('no-debug')) {
                $arguments['--no-debug'] = true;
            }

            if ($input->getOption('force')) {
                $arguments['--force'] = true;
            }

            $command = $this->getApplication()->find('assetic:watch');

            return $command->run(new ArrayInput($arguments), $stdout);
        }

        // print the header
        $stdout->writeln(sprintf('Dumping all <comment>%s</comment> assets.', $input->getOption('env')));
        $stdout->writeln(sprintf('Debug mode is <comment>%s</comment>.', $this->am->isDebug() ? 'on' : 'off'));
        $stdout->writeln('');

        if ($this->spork) {
            $batch = $this->spork->createBatchJob(
                $this->am->getNames(),
                new ChunkStrategy($input->getOption('forks'))
            );

            $self = $this;
            $batch->execute(function ($name) use ($self, $stdout) {
                $self->dumpAsset($name, $stdout);
            });
        } else {
            foreach ($this->am->getNames() as $name) {
                $this->dumpAsset($name, $stdout);
            }
        }
    }
}