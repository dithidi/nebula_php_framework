<?php

namespace Nebula\Console\Commands\Queue;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nebula\Accessors\DB;

class Migrate extends Command
{
    /**
     * The command name for the console interface.
     *
     * @var string
     */
    protected static $defaultName = 'queue:migrate';

    /**
     * Executes the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param Symfony\Component\Console\Input\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check for jobs table. Create if not present.
        $jobTableCheck = DB::raw("show full tables where Table_Type LIKE 'BASE TABLE' AND Tables_in_".config('database.name')." LIKE 'jobs'")->get();

        if (empty($jobTableCheck[0])) {
            $results = DB::raw(file_get_contents(base_path('vendor/bande/mvc_framework/src/Nebula/Database/Migration/jobs-schema.sql')))->get();
            $output->writeln('<fg=green>Jobs table has been successfully added.</>');
        } else {
            $output->writeln('<fg=green>Jobs table already exists.</>');
        }

        return 1;
    }
}