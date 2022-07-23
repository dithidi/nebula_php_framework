<?php

namespace Nebula\Accessors;

abstract class Accessor {
    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getInstance();

        // Add optional preprocessing method
        if (method_exists($instance, 'accessorPreProcess')) {
            $instance->accessorPreProcess();
        }

        return $instance->$method(...$args);
    }

    /**
     * Determines how to access the class instance.
     *
     * @return mixed
     */
    protected static function getInstance()
    {
        $instance = app()->classes[static::defineClass()];

        if (!$instance) {
            throw new \RuntimeException('An accessor root has not been set.', 500);
        }

        return $instance;
    }

    /**
     * Indicates the class name for the accessor.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function defineClass()
    {
        throw new \RuntimeException('Accessor does not implement defineClass method.', 500);
    }
}
