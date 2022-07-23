<?php

namespace Nebula\Console\Commands\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nebula\Accessors\DB;
use Nebula\Database\Models\Migration;

class Migrate extends Command {
    protected static $defaultName = 'db:migrate';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=yellow>Executing database migrations...</>');

        // Check for migrations table. Create if not present.
        $migrationCheck = DB::raw("show full tables where Table_Type LIKE 'BASE TABLE' AND Tables_in_".config('database.name')." LIKE 'migrations'")->get();

        if (empty($migrationCheck[0])) {
            $results = DB::raw(file_get_contents(base_path('vendor/bande/mvc_framework/src/Nebula/Database/Migration/migration-schema.sql')))->get();
        }

        $migrationsPath = base_path('database/migrations');

        // Gets the array of migration files
        $files = [];
        if (is_dir($migrationsPath)) {
            $files = scandir($migrationsPath);
        }

        if (empty($files[2])) {
            $output->writeln('<fg=red>No migration files were found.</>');
            return 1;
        }

        // Remove the first 2 entries from the array (. and .. from scandir results)
        $files = array_slice($files, 2);

        // Loop through migration files and run if no record in migration table has been found
        for ($i=0; $i < count($files); $i++) {
            $migration = Migration::where('filename', $files[$i])->first();

            if (!empty($migration)) {
                continue;
            }

            try {
                $results = DB::raw(file_get_contents("$migrationsPath/{$files[$i]}"))->get();
            } catch (\Exception $e) {
                $output->writeln('<fg=red>There was an issue running the migration.</>');
                return 1;
            }

            Migration::create([
                'filename' => $files[$i]
            ]);
        }

        $output->writeln('<fg=green>Migrations have been successfully run.</>');
        return 1;
    }
}
