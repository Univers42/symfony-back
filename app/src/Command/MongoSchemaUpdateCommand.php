<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SchemaManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ensures every mapped mongo document has its collection + declared indexes
 * present. Idempotent. Mirrors `doctrine:schema:update --force` for ODM.
 */
#[AsCommand(name: 'app:mongo:schema:update', description: 'Create mongo collections and indexes for all mapped documents.')]
final class MongoSchemaUpdateCommand extends Command
{
    public function __construct(private readonly DocumentManager $dm)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sm = $this->dm->getSchemaManager();
        \assert($sm instanceof SchemaManager);

        $io->writeln('Creating collections...');
        $sm->createCollections();
        $io->writeln('Creating indexes...');
        $sm->ensureIndexes();
        $io->success('Mongo schema synchronized.');

        return Command::SUCCESS;
    }
}
