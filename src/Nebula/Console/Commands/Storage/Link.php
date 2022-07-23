<?php

namespace Nebula\Console\Commands\Storage;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends Command {
    protected static $defaultName = 'storage:link';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            exec("cd " . base_path('public') . " && " . "ln -s ../storage/app/public storage");
        } catch (\Exception $e) {
            $output->writeln('<fg=red>Storage link failed to create!</>');
        }

        $output->writeln('<fg=green>Storage link has been created!</>');
    }
}
