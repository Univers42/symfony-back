<?php

declare(strict_types=1);

namespace App\Command;

use App\Baas\Loader\ModelLoader;
use MongoDB\Client;
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
        private readonly Client $client,
        private readonly string $mongoDatabase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $models = $this->loader->loadAll();
        $database = $this->client->selectDatabase($this->mongoDatabase);

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

            // Wipe collection for deterministic re-seed.
            $collection = $database->selectCollection($model->table);
            $collection->deleteMany([]);

            $documents = [];
            foreach ($fixed as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $documents[] = $this->document($row);
                $totalInserted++;
            }

            if ($documents !== []) {
                $collection->insertMany($documents);
            }

            $io->writeln(\sprintf('  - %s: seeded %d docs', $model->name, \count($fixed)));
        }

        $io->success(\sprintf('Inserted %d mongo documents.', $totalInserted));

        return Command::SUCCESS;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function document(array $row): array
    {
        foreach ($row as $key => $value) {
            if (\is_string($key) && \str_starts_with($key, '$')) {
                throw new \InvalidArgumentException('Mongo operator keys are not allowed in seed data.');
            }
            if (\is_string($value) && \str_starts_with($value, 'datetime:')) {
                $class = 'MongoDB\\BSON\\UTCDateTime';
                $row[$key] = new $class(new \DateTimeImmutable(substr($value, 9)));
            } elseif (\is_array($value)) {
                $row[$key] = $this->document($value);
            }
        }

        return $row;
    }
}
