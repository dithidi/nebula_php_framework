<?php

namespace Nebula\Http\Middleware;

use Nebula\Exceptions\AuthException;
use Nebula\Http\Request;

class VerifyCsrfToken {
    /**
     * The middleware register handler.
     *
     * @param \Nebula\Http\Request $request The request instance.
     */
    public function handle(Request $request)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (empty($request->_token) || empty($_SESSION['_token']) || $request->_token != $_SESSION['_token']) {
                throw new AuthException("Unauthorized.", 401);
            }
        }

        return $request;
    }
}
