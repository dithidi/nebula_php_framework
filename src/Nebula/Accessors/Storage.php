<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Filesystem\StorageManager;

class Storage extends Accessor {
    /**
     * Determines how to access the class instance.
     *
     * @return mixed
     */
    protected static function getInstance()
    {
        $class = static::defineClass();

        return new $class;
    }

    /**
     * Indicates the class name for the accessor.
     *
     * @return string
     */
    protected static function defineClass()
    {
        return StorageManager::class;
    }
}
