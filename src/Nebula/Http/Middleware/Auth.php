<?php

namespace Nebula\Http\Middleware;

use Nebula\Accessors\Auth as AuthAccessor;
use Nebula\Http\Request;

class Auth {
    /**
     * The middleware register handler.
     *
     * @param \Nebula\Http\Request $request The request instance.
     */
    public function handle(Request $request)
    {
        if (empty(AuthAccessor::check()) || empty($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] != ($_SESSION['ip'] ?? 'na')) {
            header('Location: ' . (!empty($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/login?url=" . $_SERVER['REQUEST_URI']);
            exit;
        }

        return $request;
    }
}
