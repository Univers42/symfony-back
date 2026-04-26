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
 * Ensures every mongo-backed YAML model has a collection present.
 * No PHP document classes are generated or required.
 */
#[AsCommand(name: 'app:mongo:schema:update', description: 'Create mongo collections for YAML models without generated PHP documents.')]
final class MongoSchemaUpdateCommand extends Command
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
        $database = $this->client->selectDatabase($this->mongoDatabase);
        $existing = iterator_to_array($database->listCollectionNames());

        $count = 0;
        foreach ($this->loader->loadAll() as $model) {
            if (!$model->isMongo() || in_array($model->table, $existing, true)) {
                continue;
            }
            $database->createCollection($model->table);
            $count++;
            $io->writeln(sprintf('  - collection %s created', $model->table));
        }

        $io->success(sprintf('Mongo schema synchronized (%d created).', $count));

        return Command::SUCCESS;
    }
}
