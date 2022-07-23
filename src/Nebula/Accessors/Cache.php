<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Cache\CacheManager;

class Cache extends Accessor {
    protected static function defineClass()
    {
        return CacheManager::class;
    }
}
