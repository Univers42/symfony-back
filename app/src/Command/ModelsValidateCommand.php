<?php

declare(strict_types=1);

namespace App\Command;

use App\Baas\Loader\ModelLoader;
use App\Baas\Loader\ModelValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:models:validate', description: 'Validate /models/*.yaml files.')]
final class ModelsValidateCommand extends Command
{
    public function __construct(
        private readonly ModelLoader $loader,
        private readonly ModelValidator $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $models = $this->loader->loadAll();
        $io->writeln(\sprintf('Loaded <info>%d</info> models.', \count($models)));

        $errors = $this->validator->validate($models);
        if ($errors === []) {
            $io->success('All models are valid.');

            return Command::SUCCESS;
        }
        foreach ($errors as $err) {
            $io->error($err);
        }

        return Command::FAILURE;
    }
}
