<?php

namespace Nebula\Exceptions;

use App\Exceptions\Handler;
use Nebula\Exceptions\Logger;

class ExceptionHandler {
    /**
     * The filepaths of the error logs.
     *
     * @var array
     */
    protected $logFilepaths = [];

    /**
     * The ignored exception types for logging.
     *
     * @var array
     */
    protected $ignored = [
        \Nebula\Exceptions\PageNotFoundException::class
    ];

    /**
     * Create a new class instance.
     *
     * @param \Nebula\Exceptions\Logger $logger The logger instance.
     * @return void
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;

        error_reporting(-1);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handles an exception by logging.
     *
     * @param \Throwable $exception The throwable exception instance.
     * @return void
     */
    public function handleException(\Throwable $exception)
    {
        $this->hasError = true;
        $this->exception = $exception;

        $message = $exception->getMessage() . "\n" . $exception->getTraceAsString();

        // Only logged the error if not in the ignore list
        $exceptionsToIgnore = array_merge($this->ignored, Handler::$ignored ?? []);
        if (!in_array(get_class($exception), $exceptionsToIgnore)) {
            $this->logger->log($message);
        }
    }

    /**
     * Handles an error by logging.
     *
     * @param int $type The error type.
     * @param string $message The error message.
     * @param string $file The file name where the error occurred.
     * @param int $line The line number where the error occurred.
     * @return void
     *
     * @throws \Exception
     */
    public function handleError(int $type, string $message, string $file, int $line) {
        $message = "Error occurred in file $file, line $line. $message";

        throw new \Exception($message, 500);
    }

    /**
     * Handles the shutdown of the application.
     *
     * @return mixed
     */
    public function handleShutdown()
    {
        // If there has been an error, trigger app-level exception handler.
        if (!empty($this->hasError)) {
            return \App\Exceptions\Handler::handle($this->exception);
            exit;
        }

        exit;
    }
}