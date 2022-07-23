<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Routing\Router as AppRouter;

class Router extends Accessor {
    protected static function defineClass()
    {
        return AppRouter::class;
    }
}
