<?php

namespace Nebula\Console\Commands\Schedule;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{
    protected static $defaultName = 'schedule:run';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (class_exists("\\App\\Console\\Kernel")) {
            $kernel = app()->classes[\App\Console\Kernel::class];
            $kernel->buildSchedule();
            $kernel->run();

            return 1;
        }

        return 0;
    }
}
