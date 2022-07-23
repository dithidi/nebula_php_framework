<?php

namespace Nebula\Exceptions;

class Handler {
    /**
     * Handles the application exception.
     *
     * @param \Throwable $exception The throwable exception instance.
     * @return mixed
     */
    public static function handle(\Throwable $exception)
    {
        // If app is in debug mode, register the exception debugger
        if (!empty(config('app.debug')) && empty(config('appRunningInConsole'))) {
            $whoops = new \Whoops\Run;
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);

            header('Content-Type: text/html');
            $html = $whoops->handleException($exception);
            echo $html;
            exit;
        }
    }
}
