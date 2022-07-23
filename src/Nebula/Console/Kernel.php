<?php

namespace Nebula\Console;

use Carbon\Carbon;

class Kernel {
    /**
     * Sets a list of global commands available to the console
     * application.
     *
     * @var array
     */
    protected $globalCommands = [
        \Nebula\Console\Commands\Config\Cache::class,
        \Nebula\Console\Commands\Config\Clear::class,
        \Nebula\Console\Commands\Database\Migrate::class,
        \Nebula\Console\Commands\Route\Cache::class,
        \Nebula\Console\Commands\Route\Clear::class,
        \Nebula\Console\Commands\Route\ListRoutes::class,
        \Nebula\Console\Commands\Schedule\Run::class,
        \Nebula\Console\Commands\Storage\Link::class,
        \Nebula\Console\Commands\Synthesize::class,
        \Nebula\Console\Commands\Queue\Migrate::class,
        \Nebula\Console\Commands\Queue\Work::class,
        \Nebula\Console\Commands\Websockets\Server::class
    ];

    /**
     * Holds the current command to run.
     *
     * @var string
     */
    protected $commandName = '';

    /**
     * Holds the current command run status.
     *
     * @var bool
     */
    protected $runNow = false;

    /**
     * The array of schedule data.
     *
     * @var array
     */
    protected $schedule = [];

    /**
     * The current Carbon datetime.
     *
     * @var \Carbon\Carbon
     */
    protected $currentCarbon;

    public function __construct($application)
    {
        $this->currentCarbon = Carbon::now();

        foreach ($this->globalCommands as $command) {
            $application->add(new $command);
        }

        if (!empty($this->appCommands)) {
            foreach ($this->appCommands as $command) {
                $application->add(new $command);
            }
        }
    }

    /**
     * Runs a command.
     *
     * @return bool
     */
    public function run()
    {
        if (empty($this->schedule)) {
            return false;
        }

        foreach ($this->schedule as $scheduledTask) {
            $result = shell_exec("/usr/local/bin/php " . base_path('fusion') . " {$scheduledTask['commandName']}");

            if (!empty($result)) {
                logError("Error occurred in cronjob: {$scheduledTask['commandName']}. ERROR: $result");
            }
        }

        return true;
    }

    /**
     * Defines a command.
     *
     * @param string $commandName The name of the stellar-fusion command.
     * @param mixed $data The data to pass to the command.
     * @return \Nebula\Console\Kernel
     */
    protected function command($commandName, $data = null)
    {
        $this->commandName = $commandName;
        $this->data = $data;

        return $this;
    }

    /**
     * Schedules a command.
     *
     * @return bool
     */
    protected function schedule()
    {
        if (empty($this->commandName) || empty($this->runNow)) {
            return false;
        }

        $this->schedule[] = [
            'commandName' => $this->commandName,
            'data' => $this->data ?? null
        ];

        return true;
    }

    /**
     * Sets a run time for every minute.
     *
     * @return \Nebula\Console\Kernel
     */
    protected function everyMinute()
    {
        $this->runNow = true;

        return $this;
    }

    /**
     * Sets a run time for every 5 minutes.
     *
     * @return \Nebula\Console\Kernel
     */
    protected function everyFiveMinutes()
    {
        $this->runNow = false;

        $minuteNow = $this->currentCarbon->format('i');

        if ($minuteNow == 0 || $minuteNow % 5 == 0) {
            $this->runNow = true;
        }

        return $this;
    }

    /**
     * Sets a run time for every hour.
     *
     * @return \Nebula\Console\Kernel
     */
    protected function hourly()
    {
        $this->runNow = false;

        $minuteNow = $this->currentCarbon->format('i');

        if ($minuteNow == '00') {
            $this->runNow = true;
        }

        return $this;
    }

    /**
     * Sets a run time for every hour at X hour.
     *
     * @param int $hour The hour number (0-23)
     * @return \Nebula\Console\Kernel
     */
    protected function hourlyAt($hour)
    {
        $this->runNow = false;

        $hourNow = $this->currentCarbon->format('H');
        $minuteNow = $this->currentCarbon->format('i');

        if ($hourNow == $hour && $minuteNow == '00') {
            $this->runNow = true;
        }

        return $this;
    }
}
