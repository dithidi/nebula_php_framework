<?php

namespace Nebula\Console\Commands\Route;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Clear extends Command {
    protected static $defaultName = 'route:clear';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (file_exists(storage_path('framework/cache/routes.json'))) {
            unlink(storage_path('framework/cache/routes.json'));
            $output->writeln('<fg=green>Route cache has been cleared!</>');

            return 1;
        } else {
            $output->writeln('<fg=red>No Route cache was not found.</>');

            return 0;
        }
    }
}
