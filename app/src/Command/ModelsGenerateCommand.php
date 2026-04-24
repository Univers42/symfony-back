<?php

declare(strict_types=1);

namespace App\Command;

use App\Baas\Generator\DocumentGenerator;
use App\Baas\Generator\EntityGenerator;
use App\Baas\Generator\FixturesGenerator;
use App\Baas\Generator\GeneratedFileWriter;
use App\Baas\Generator\MongoRegistryGenerator;
use App\Baas\Loader\ModelLoader;
use App\Baas\Loader\ModelValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:models:generate', description: 'Generate Doctrine entities, ODM documents, repositories, fixtures and the mongo registry from /models.')]
final class ModelsGenerateCommand extends Command
{
    public function __construct(
        private readonly ModelLoader $loader,
        private readonly ModelValidator $validator,
        private readonly EntityGenerator $entityGen,
        private readonly DocumentGenerator $documentGen,
        private readonly FixturesGenerator $fixturesGen,
        private readonly MongoRegistryGenerator $registryGen,
        private readonly GeneratedFileWriter $writer,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite hand-written files (DANGEROUS).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $models = $this->loader->loadAll();
        $errors = $this->validator->validate($models);
        if ($errors !== []) {
            foreach ($errors as $e) {
                $io->error($e);
            }

            return Command::FAILURE;
        }
        $io->writeln(\sprintf('Generating from <info>%d</info> models...', \count($models)));

        $items = [];
        foreach ($models as $m) {
            if ($m->isPostgres()) {
                $items[] = [
                    'path'     => $this->projectDir . '/src/Entity/' . $m->name . '.php',
                    'contents' => $this->entityGen->generate($m),
                ];
                $items[] = [
                    'path'     => $this->projectDir . '/src/Repository/' . $m->name . 'Repository.php',
                    'contents' => $this->entityGen->generateRepository($m),
                ];
                if (!empty($m->seeds)) {
                    $items[] = [
                        'path'     => $this->projectDir . '/src/DataFixtures/Generated/' . $m->name . 'Fixtures.php',
                        'contents' => $this->fixturesGen->generate($m),
                    ];
                }
            } elseif ($m->isMongo()) {
                $items[] = [
                    'path'     => $this->projectDir . '/src/Document/' . $m->name . '.php',
                    'contents' => $this->documentGen->generate($m),
                ];
            }
        }

        // Always emit the mongo registry, even if there are no mongo models.
        $items[] = [
            'path'     => $this->projectDir . '/src/Baas/Generated/MongoResourceRegistry.php',
            'contents' => $this->registryGen->generate($models),
        ];

        $result = $this->writer->writeMany($items, $force);

        $io->writeln(\sprintf('Wrote <info>%d</info> files, skipped <comment>%d</comment> hand-written files.', $result['written'], $result['skipped']));
        foreach ($result['paths_skipped'] as $p) {
            $io->writeln('  <comment>SKIP</comment> ' . $p);
        }

        return Command::SUCCESS;
    }
}
