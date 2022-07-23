<?php

namespace Nebula\Exceptions;

class Logger {
    /**
     * Holds the active logging channel name.
     *
     * @var string
     */
    public $tempChannel = 'error';

    /**
     * Create a new class instance.
     *
     * @param array $loggingConfig The app level logging configuration.
     * @return void
     */
    public function __construct($loggingConfig)
    {
        $this->loggingConfig = $loggingConfig;
    }

    /**
     * Creates a logging entry into the desired log file.
     *
     * @param string|array $message The message to log.
     * @param string $errorPrefix Indicates the type of message that is being logged.
     * @return void
     */
    public function log($message = '', $errorPrefix = 'ERROR')
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES);
        }

        file_put_contents(
            $this->loggingConfig['channels'][$this->tempChannel]['path'],
            "[" . date('Y-m-d H:i:s') . "] $errorPrefix: $message\n",
            FILE_APPEND
        );

        $this->tempChannel = 'error';
    }

    /**
     * Sets the active logging channel name.
     *
     * @param string $name The name of the channel.
     * @return \Nebula\Exceptions\Logger
     */
    public function channel($name = '')
    {
        $this->tempChannel = $name;

        return $this;
    }

    /**
     * Creates an error log entry.
     *
     * @param string|array $message The message to log.
     * @return void
     */
    public function error($message = '')
    {
        $this->log($message, 'ERROR');
    }

    /**
     * Creates an info log entry.
     *
     * @param string|array $message The message to log.
     * @return void
     */
    public function info($message = '')
    {
        $this->log($message, 'INFO');
    }
}
