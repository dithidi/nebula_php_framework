<?php

namespace Nebula\Console\Commands\Route;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nebula\Routing\Router;

class ListRoutes extends Command {
    protected static $defaultName = 'route:list';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (app()->classes[Router::class]->routes as $route) {
            $output->writeln("<fg=green>{$route['path']} | {$route['type']} | {$route['controller']}@{$route['action']}</>");
        }

        return 1;
    }
}
