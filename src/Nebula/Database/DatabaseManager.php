<?php

namespace Nebula\Database;

use PDO;

class DatabaseManager {
    /**
     * The DB host name.
     *
     * @var string
     */
    private $host;

    /**
     * The DB port.
     *
     * @var int
     */
    private $post;

    /**
     * The DB name.
     *
     * @var string
     */
    private $name;

    /**
     * The DB username.
     *
     * @var string
     */
    private $username;

    /**
     * The DB password.
     *
     * @var string
     */
    private $password;

    /**
     * The PDO connection instance.
     *
     * @var \PDO
     */
    private $dbConnection;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        $dbConfig = config('database');

        $this->host = $dbConfig['host'];
        $this->port = $dbConfig['port'];
        $this->name = $dbConfig['name'];
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];

        // Set the options
        $options = [];
        if (!empty(config('runningInConsole'))) {
            $options = [
                // PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => true
            ];
        }

        // Connect to database
        $this->dbConnection = new PDO(
            "mysql:host={$this->host};port={$this->port};dbname={$this->name}",
            $this->username,
            $this->password,
            $options
        );

        $this->dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Returns the PDO instance.
     *
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->dbConnection;
    }
}
