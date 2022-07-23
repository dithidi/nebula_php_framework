<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Auth\AuthManager;

class Auth extends Accessor {
    protected static function defineClass()
    {
        return AuthManager::class;
    }
}
