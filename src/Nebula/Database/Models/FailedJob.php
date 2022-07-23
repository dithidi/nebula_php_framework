<?php

namespace Nebula\Database\Models;

use Nebula\Database\Model;
use PDO;

class FailedJob extends Model
{
    protected $connection = '';
    protected $table = 'failed_jobs';
    protected $primaryKey = 'id';

    /**
     * Create a new class instance.
     *
     * @param \PDO $dbConnection The PDO instance.
     * @param array $options The options array.
     * @return void
     */
    public function __construct(PDO $dbConnection = null, $options = [])
    {
        $this->connection = config('database.name');
        parent::__construct($dbConnection, $options);
    }
}
