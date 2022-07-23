<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Exceptions\Logger;

class Log extends Accessor {
    protected static function defineClass()
    {
        return Logger::class;
    }
}
