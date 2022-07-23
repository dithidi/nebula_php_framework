<?php

namespace Nebula\Jobs;

use Nebula\Database\Models\Job as JobModel;
use Carbon\Carbon;

class Job
{
    /**
     * Dispatches a job to the queue.
     *
     * @param mixed $args The optional arguments for the job.
     * @return bool
     */
    public static function dispatch(...$args)
    {
        JobModel::create([
            'queue' => static::$queue ?? 'default',
            'class' => get_called_class(),
            'payload' => serialize($args)
        ]);
    }
}
