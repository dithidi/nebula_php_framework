<?php

namespace Nebula\Cache;

class CacheManager
{
    /**
     * The cache configuration array.
     *
     * @var array
     */
    public $cacheConfig;

    /**
     * The Redis client instance.
     *
     * @var \Redis
     */
    protected $RedisClient;

    /**
     * Indicates whether Redis is connected.
     *
     * @var bool
     */
    protected $redisConnected = false;

    /**
     * Create a new class instance.
     *
     * @param array $cacheConfig The cache configuration array.
     * @return void
     */
    public function __construct($cacheConfig = null)
    {
        $this->cacheConfig = $cacheConfig;

        if (class_exists('\Redis') && !empty($this->cacheConfig)) {
            try {
                $this->RedisClient = new \Redis();
                $this->redisConnected = $this->RedisClient->connect($this->cacheConfig['entrypoint'], $this->cacheConfig['port']);
            } catch (\RedisException $e) {
                $this->redisConnected = false;
            }
        }
    }

    /**
     * Gets cache data by key.
     *
     * @param string $key The key of the cache.
     * @param mixed $default The default return.
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (empty($this->redisConnected)) {
            return null;
        }

        $results = $this->RedisClient->get("{$this->cacheConfig['prefix']}:$key");

        if (!empty($results)) {
            $results = unserialize($results);
        } else {
            $results = $default;
        }

        return $results;
    }

    /**
     * Sets cache key with data.
     *
     * @param string $key The key of the cache.
     * @param mixed $data The data to save in cache.
     * @param int $seconds The number of seconds to store in cache.
     * @return mixed
     */
    public function set($key, $data, $seconds = 900)
    {
        if (empty($this->redisConnected)) {
            return null;
        }

        return $this->RedisClient->set("{$this->cacheConfig['prefix']}:$key", serialize($data), $seconds);
    }

    /**
     * Gets cache if available, otherwise sets the cache and returns the value.
     *
     * @param string $key The key of the cache.
     * @param int $seconds The number of seconds to store in cache.
     * @param callback $callback The callback to set the cache if not present.
     * @return mixed
     */
    public function remember($key, $seconds, $callback)
    {
        if (empty($this->redisConnected)) {
            return null;
        }

        $results = $this->get($key);

        if (!empty($results)) {
            return $results;
        }

        $results = $callback();

        if (!empty($results)) {
            $this->set($key, $results, $seconds);
        }

        return $results ?? null;
    }

    /**
     * Forgets a cache key if it exists.
     *
     * @param string $key The key of the cache.
     * @return bool
     */
    public function forget($key)
    {
        if (empty($this->redisConnected)) {
            return null;
        }

        return $this->RedisClient->unlink("{$this->cacheConfig['prefix']}:$key");
    }
}
