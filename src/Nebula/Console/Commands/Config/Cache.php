<?php

namespace Nebula\Console\Commands\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cache extends Command {
    protected static $defaultName = 'config:cache';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents(storage_path('framework/cache/config.json'), json_encode(app()->config));

        $output->writeln('<fg=green>Configuration has been cached!</>');
    }
}
