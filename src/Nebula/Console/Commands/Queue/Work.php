<?php

namespace Nebula\Console\Commands\Queue;

use Nebula\Database\Models\{FailedJob, Job};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Work extends Command
{
    /**
     * The command name for the console interface.
     *
     * @var string
     */
    protected static $defaultName = 'queue:work';

    /**
     * Indicates whether to continue running the queue worker.
     *
     * @var bool
     */
    protected $keepRunning = true;

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'The name of the queue.');
    }

    /**
     * Executes the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param Symfony\Component\Console\Input\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handlePcntlSignals();

        $started = time();

        $queue = $input->getArgument('queue') ?? 'default';

        while($this->keepRunning) {
            $nextJob = Job::orderBy('id', 'desc')
                ->where('in_process', 0)
                ->where('queue', $queue)
                ->first();

            if (!empty($nextJob)) {
                $nextJob->in_process = 1;
                $nextJob->save();

                $className = $nextJob->class;

                try {
                    $jobClass = new $className;
                    $jobClass->load(...unserialize($nextJob->payload));
                    $results = $jobClass->handle();

                    Job::where('id', $nextJob->id)->delete();
                } catch (\Exception $e) {
                    // Handle failed jobs
                    FailedJob::create([
                        'queue' => $nextJob->queue,
                        'class' => $nextJob->class,
                        'payload' => $nextJob->payload,
                        'error' => $e->getMessage()
                    ]);

                    Job::where('id', $nextJob->id)->delete();
                }
            } else {
                // If no jobs are available, sleep for 5 seconds
                sleep(5);
            }

            // Check to see if the script has been running for too long (2 hours).
            // Once this script has finished, supervisor will restart a fresh instance.
            if (time() - $started >= 60 * 60 * 2) {
                $this->keepRunning = false;
            }
        }

        return 1;
    }

    /**
     * Handles various pcntl signals for shutdown.
     *
     * @return void
     */
    protected function handlePcntlSignals()
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() {
            $this->keepRunning = false;
        });

        pcntl_signal(SIGINT, function() {
            $this->keepRunning = false;
        });
    }
}