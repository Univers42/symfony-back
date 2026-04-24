<?php

declare(strict_types=1);

namespace App\Command;

use App\Baas\Generated\MongoResourceRegistry;
use App\Baas\Loader\ModelLoader;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds mongo collections from the `seeds.fixed` block of every mongo-backed
 * model file. Existing collections are wiped first to keep the seed
 * deterministic — this is intended for dev / CI only.
 */
#[AsCommand(name: 'app:mongo:seed', description: 'Seed mongo collections from model YAML seeds.')]
final class MongoSeedCommand extends Command
{
    public function __construct(
        private readonly ModelLoader $loader,
        private readonly DocumentManager $dm,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $models = $this->loader->loadAll();
        $registry = MongoResourceRegistry::all();

        $totalInserted = 0;
        foreach ($models as $model) {
            if (!$model->isMongo()) {
                continue;
            }
            $fixed = (array) ($model->seeds['fixed'] ?? []);
            if ($fixed === []) {
                $io->writeln(\sprintf('  - %s: no seeds, skipped.', $model->name));
                continue;
            }

            $class = $registry[$model->table] ?? null;
            if ($class === null) {
                $io->warning(\sprintf('No registry entry for "%s"; did you run app:models:generate?', $model->table));
                continue;
            }

            // Wipe collection for deterministic re-seed.
            $this->dm->getDocumentCollection($class)->deleteMany([]);

            foreach ($fixed as $row) {
                $doc = new $class();
                foreach ($row as $field => $value) {
                    $setter = 'set' . ucfirst((string) $field);
                    if (!method_exists($doc, $setter)) {
                        continue;
                    }
                    if (\is_string($value) && \str_starts_with($value, 'datetime:')) {
                        $doc->$setter(new \DateTimeImmutable(substr($value, 9)));
                        continue;
                    }
                    $doc->$setter($value);
                }
                $this->dm->persist($doc);
                $totalInserted++;
            }

            $io->writeln(\sprintf('  - %s: seeded %d docs', $model->name, \count($fixed)));
        }

        $this->dm->flush();
        $io->success(\sprintf('Inserted %d mongo documents.', $totalInserted));

        return Command::SUCCESS;
    }
}
