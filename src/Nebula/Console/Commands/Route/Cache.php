<?php

namespace Nebula\Console\Commands\Route;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nebula\Routing\Router;

class Cache extends Command {
    protected static $defaultName = 'route:cache';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents(storage_path('framework/cache/routes.json'), json_encode(app()->classes[Router::class]->routes));

        $output->writeln('<fg=green>Routes have been cached!</>');

        return 1;
    }
}
