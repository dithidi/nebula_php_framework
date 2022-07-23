<?php

namespace Nebula\Console\Commands\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Clear extends Command {
    protected static $defaultName = 'config:clear';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (file_exists(storage_path('framework/cache/config.json'))) {
            unlink(storage_path('framework/cache/config.json'));
            $output->writeln('<fg=green>Configuration has been cleared!</>');

            return 1;
        } else {
            $output->writeln('<fg=red>No configuration cache was found.</>');

            return 0;
        }
    }
}
