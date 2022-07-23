<?php

namespace Nebula\Collections;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Nebula\Database\Model;

class Collection implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    /**
     * The items of the collection.
     *
     * @var array
     */
    private $array = [];

    /**
     * Creates a new class instance.
     *
     * @param array $array An optional array.
     * @return \Nebula\Collection\Collection
    */
    public function __construct($array = [])
    {
        $this->array = $array;
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->all());
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __get($key)
    {
        return $this->array[$key] ?? null;
    }

    /**
     * Indicates what should be displayed when a collection is json encoded.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->array;
    }

    /**
     * Retrieves an external iterator.
     *
     * This method is required for implementing IteratorAggregate and accessing
     * the array as an iterable.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->array);
    }

    /**
     * Returns the collection as an array.
     *
     * @return array
     */
    public function all()
    {
        return $this->array;
    }

    /**
     * Handles cleaning of collection models.
     *
     * @return \Nebula\Collections\Collection
     */
    public function clean()
    {
        // Handle nested relational cleansing
        foreach ($this->array as &$object) {
            if (get_parent_class($object) == Model::class) {
                $object->clean();
            } else {
                foreach ($object as &$obj) {
                    if (get_parent_class($obj) == Model::class) {
                        $obj->clean();
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Counts the number of elements in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->array);
    }

    /**
     * Returns the first element in the collection.
     *
     * @return mixed
     */
    public function first()
    {
        if (empty($this->array)) {
            return null;
        }

        $duplicate = $this->array;
        return array_shift($duplicate);
    }

    /**
     * Determines if the collection contains a given key.
     *
     * @param string|array $keys One or more keys.
     * @return bool True if the key(s) exist(s) in the collection.
     */
    public function has($keys)
    {
        foreach ((array) $keys as $key) {
            if (!array_key_exists($key, $this->array)) {
                return false;
            }
        }

        return true;
    }


    /**
     * Returns the last element in the collection.
     *
     * @return mixed
     */
    public function last()
    {
        if (empty($this->array)) {
            return null;
        }

        $duplicate = $this->array;
        return array_pop($duplicate);
    }

    /**
     * Returns a new collection of the selected key.
     *
     * @param mixed $key The key to pluck from the collection.
     * @return \Nebula\Collections\Collection
     */
    public function pluck($key)
    {
        $results = [];

        foreach ($this->array as $entry) {
           $results[] = isset($entry->$key) ? $entry->$key : null;
        }

        return collect($results);
    }

    /**
     * Returns a new collection of the selected keys.
     *
     * @param array $keys The array of keys to pluck from the collection.
     * @return \Nebula\Collections\Collection
     */
    public function pluckMany($keys)
    {
        $results = [];

        foreach ($this->array as $entry) {
            $newArray = [];

            foreach ($keys as $key) {
                $newArray[$key] = $entry->$key ?? null;
            }

           $results[] = $newArray;
        }

        return collect($results);
    }

    /**
     * Performs an extraction using a where clause.
     *
     * @param $array $args The arguments for the filter.
     * @return \Nebula\Collections\Collection
     */
    public function where(...$args)
    {
        $results = [];

        if (empty($this->array)) {
            return collect([]);
        }

        // Handle arrays
        $results = array_filter($this->array, function($item) use ($args) {
            if (is_array($item)) {
                if (count($args) == 2) {
                    return $item[$args[0]] == $args[1];
                } elseif ($args[1] == '=') {
                    return $item[$args[0]] == $args[2];
                } elseif ($args[1] == '>') {
                    return $item[$args[0]] > $args[2];
                } elseif ($args[1] == '>=') {
                    return $item[$args[0]] >= $args[2];
                } elseif ($args[1] == '<') {
                    return $item[$args[0]] < $args[2];
                } elseif ($args[1] == '<=') {
                    return $item[$args[0]] <= $args[2];
                } elseif ($args[1] == '!=') {
                    return $item[$args[0]] != $args[2];
                } else {
                    throw new \Exception("{$args[1]} is not an acceptable evaluation for a where collection clause.", 500);
                }
            } else {
                // Handle objects/models
                if (count($args) == 2) {
                    return $item->{$args[0]} == $args[1];
                } elseif ($args[1] == '=') {
                    return $item->{$args[0]} == $args[2];
                } elseif ($args[1] == '>') {
                    return $item->{$args[0]} > $args[2];
                } elseif ($args[1] == '>=') {
                    return $item->{$args[0]} >= $args[2];
                } elseif ($args[1] == '<') {
                    return $item->{$args[0]} < $args[2];
                } elseif ($args[1] == '<=') {
                    return $item->{$args[0]} <= $args[2];
                } elseif ($args[1] == '!=') {
                    return $item->{$args[0]} != $args[2];
                } else {
                    throw new \Exception("{$args[1]} is not an acceptable evaluation for a where collection clause.", 500);
                }
            }
        });

        return collect(array_values($results));
    }

    /**
     * Filters collection based on class name.
     *
     * @param string $className The class name for comparison.
     * @return \Nebula\Collections\Collection
     */
    public function whereClass($className)
    {
        $results = [];

        $results = array_filter($this->array, function($item) use ($className) {
            return "\\" . get_class($item) == $className;
        });

        return collect($results);
    }

    /**
     * Indicates if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->array);
    }

    /**
     * Indicates if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return !empty($this->array);
    }

    /**
     * Groups a collection by a key.
     *
     * @param string $key The name of the key.
     * @return \Nebula\Collections\Collection
     */
    public function groupBy($key)
    {
        $results = $this->array;

        // Get list of unique key entries
        $uniqueKeys = array_unique($this->pluck($key)->all());

        $newData = [];

        foreach ($uniqueKeys as $k) {
            $newData[] = $this->where($key, $k);
        }

        return collect($newData);
    }

    /**
     * Sorts the collection by a given key.
     *
     * @param $array $key The key for sorting.
     * @return \Nebula\Collections\Collection
     */
    public function sortBy($key)
    {
        $results = $this->array;

        usort($results, function($a, $b) use ($key) {
            if (is_array($a)) {
                return $a[$key] > $b[$key];
            } else {
                return $a->key > $b->key;
            }
        });

        return collect($results);
    }

    /**
     * Sorts the collection by 2 keys ascending.
     *
     * @param array $keys The keys for sorting.
     * @param string $directionOne The direction for the first sort.
     * @param string $directionTwo The direction for the second sort.
     * @return \Nebula\Collections\Collection
     */
    public function sortByMulti($keys, $directionOne = 'asc', $directionTwo = 'desc')
    {
        $results = $this->array;

        usort($results, function($a, $b) use ($keys, $directionOne, $directionTwo) {
            if (is_array($a)) {
                if ($directionOne == 'asc' && $directionTwo == 'asc') {
                    return $a[$keys[0]] <=> $b[$keys[0]] ?: $a[$keys[1]] <=> $b[$keys[1]];
                } elseif ($directionOne == 'asc' && $directionTwo == 'desc') {
                    return $a[$keys[0]] <=> $b[$keys[0]] ?: $b[$keys[1]] <=> $a[$keys[1]];
                } elseif ($directionOne == 'desc' && $directionTwo == 'asc') {
                    return $b[$keys[0]] <=> $a[$keys[0]] ?: $a[$keys[1]] <=> $b[$keys[1]];
                } else {
                    return $b[$keys[0]] <=> $a[$keys[0]] ?: $b[$keys[1]] <=> $a[$keys[1]];
                }
            } else {
                if ($directionOne == 'asc' && $directionTwo == 'asc') {
                    return $a->{$keys[0]} <=> $b->{$keys[0]} ?: $a->{$keys[1]} <=> $b->{$keys[1]};
                } elseif ($directionOne == 'asc' && $directionTwo == 'desc') {
                    return $a->{$keys[0]} <=> $b->{$keys[0]} ?: $b->{$keys[1]} <=> $a->{$keys[1]};
                } elseif ($directionOne == 'desc' && $directionTwo == 'asc') {
                    return $b->{$keys[0]} <=> $a->{$keys[0]} ?: $a->{$keys[1]} <=> $b->{$keys[1]};
                } else {
                    return $b->{$keys[0]} <=> $a->{$keys[0]} ?: $b->{$keys[1]} <=> $a->{$keys[1]};
                }
            }
        });

        return collect($results);
    }

    /**
     * Sorts the collection desc by a given key.
     *
     * @param $array $key The key for sorting.
     * @return \Nebula\Collections\Collection
     */
    public function sortByDesc($key)
    {
        $results = $this->array;

        usort($results, function($a, $b) use ($key) {
            if (is_array($a)) {
                return $a[$key] < $b[$key];
            } else {
                return $a->key < $b->key;
            }
        });

        return collect($results);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        return isset($this->array[$key]);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->array[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->array[] = $value;
        } else {
            $this->array[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        unset($this->array[$key]);
    }
}
