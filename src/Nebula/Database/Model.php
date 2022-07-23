<?php

namespace Nebula\Database;

use Nebula\Accessors\DB;
use Nebula\Collections\Collection;
use Nebula\Database\{ DatabaseManager, QueryBuilder };
use Nebula\Exceptions\{ ModelException, ModelNotFoundException };
use Carbon\Carbon;
use JsonSerializable;

class Model extends QueryBuilder implements JsonSerializable
{
    /**
     * Default model primary key to 'id'.
     *
     * @var int
     */
    protected $primaryKey = 'id';

    /**
     * The static model instance.
     *
     * @var \Nebula\Database\Model
     */
    protected static $instance;

    /**
     * Holds the database values for the model instance.
     *
     * @var array
     */
    protected $modelData;

    /**
     * Determines which attributes should be hidden during a cleansing.
     */
    protected $hidden = [];

    /**
     * Holds the database values for the changed model instance attributes.
     *
     * @var array
     */
    protected $changedModelData = [];

    /**
     * Defines the model relations.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Defines the model relationships to eager load.
     *
     * @var array
     */
    protected $toEagerLoad = [];

    /**
     * Defines the model relationships to sub eager load.
     *
     * @var array
     */
    protected $toSubEagerLoad = [];

    /**
     * Holds the fetched models for subsequent queries.
     *
     * @var array
     */
    protected $models = [];

    /**
     * Indicates whether a relational query should immediately be executed.
     *
     * @var bool
     */
    protected $executeRelationalQuery = false;

    /**
     * Indicates whether a relational query should be forced.
     *
     * @var bool
     */
    protected $forceRelationalQuery = false;

    /**
     * Holds the pivot table name for many to many relationships.
     *
     * @var string
     */
    protected $pivotTableName = '';

    /**
     * Holds the pivot key name for THIS in many to many relationships.
     *
     * @var string
     */
    protected $pivotThisKeyName = '';

    /**
     * Holds the pivot key name for RELATION in many to many relationships.
     *
     * @var string
     */
    protected $pivotRelationKeyName = '';

    /**
     * Holds the sync data for belongsToMany relationship syncs.
     *
     * @var array
     */
    protected $syncData = [];

    /**
     * Indicates whether the model has timestamps.
     *
     * @var bool
     */
    public $timestamps = true;


    /**
     * Create a new class instance.
     *
     * @param \PDO $dbConnection The PDO instance.
     * @param array $options The options array.
     * @return void
     */
    public function __construct(\PDO $dbConnection = null, $options = [])
    {
        if (!empty($dbConnection)) {
            $this->dbConnection = $dbConnection;
        } else {
            // If db connection is not included, fetch from app level
            $this->dbConnection = app()->classes[\Nebula\Database\DatabaseManager::class]->getPdo();
        }

        if (empty($this->table)) {
            $tableName = explode('\\', __CLASS__);
            $this->table = strtolower(array_pop($tableName)) . 's';
        }

        // Handle existing model data
        if (!empty($options['data'])) {
            $this->modelData = $options['data'];
        }
    }

    /**
     * Handle statically called methods for chaining.
     *
     * @param string $method The name of the method.
     * @param array $args The array of data for the method.
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $className = get_called_class();

        static::$instance = new $className(app()->classes[DatabaseManager::class]->getPdo());
        return static::$instance->$method(...$args);
    }

    /**
     * Dynamically proxy attribute access to the variable.
     *
     * @param string $attribute The attribute key for input access.
     * @return mixed
     */
    public function __get($attribute)
    {
        // First check to see if forceRelationalQuery has been set. This means we need to
        // force the model to refetch the relationship.
        if (!empty($this->forceRelationalQuery) && method_exists($this, $attribute)) {
            $result = $this->$attribute();
            $this->forceRelationalQuery = false;
        }

        // Attempt to get the attribute from the modelData array
        if (empty($result) && isset($this->modelData[$attribute])) {
            $result = $this->modelData[$attribute];
        }

        // Attempt the get the data from the relations
        if (empty($result) && isset($this->relations[$attribute])) {
            $result = $this->relations[$attribute];
        }

        // Attempt to call a relational method
        if (!isset($result) && method_exists($this, $attribute)) {
            $this->executeRelationalQuery = true;
            $result = $this->$attribute();
            $this->executeRelationalQuery = false;
        }

        return isset($result) ? $result : null;
    }

    /**
     * Dynamically proxy attribute sets to the variable.
     *
     * @param string $attribute The attribute key for input access.
     * @return mixed
     */
    public function __set($attribute, $value)
    {
        if (array_key_exists($attribute, $this->relations)) {
            $this->relations[$attribute] = $value;
        } else {
            $this->modelData[$attribute] = $value;
            $this->changedModelData[$attribute] = $value;
        }

        return true;
    }

    /**
     * Overrides the default functionality of isset so that empty checks work
     * as expected.
     *
     * @param string $attribute The attribute key for isset check.
     * @return bool
     */
    public function __isset($attribute)
    {
        if (isset($this->modelData[$attribute])) {
            $result = $this->modelData[$attribute];
        }

        if (isset($this->relations[$attribute])) {
            $result = $this->relations[$attribute];
        }

        return isset($result);
    }

    /**
     * Indicates the attributes to keep during serialization.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['modelData', 'relations'];
    }

    public function jsonSerialize()
    {
        return array_merge($this->modelData, $this->relations);
    }

    /**
     * Returns the collection as an array.
     *
     * @return array
     */
    public function all()
    {
        return array_merge($this->modelData, $this->relations);
    }

    /**
     * Returns the primary key.
     *
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey ?? null;
    }

    /**
     * Unsets a value in the modelData array.
     *
     * @param string $key The key to remove.
     * @return \Nebula\Database\Model
     */
    public function unset($key)
    {
        unset($this->modelData[$key]);

        return $this;
    }

    /**
     * Gets the model by primary key.
     *
     * @param int $id The ID of the model.
     * @return array
     */
    public function _find($id)
    {
        if (empty($this->primaryKey)) {
            throw new \RuntimeException(get_called_class() . " cannot use 'find' because it does not have a primary key.", 500);
        }

        $this->limit = 1;
        $this->clauses = ["{$this->primaryKey} = ?"];
        $this->parameters = [$id];

        return $this->get('first');
    }

    /**
     * Gets the model by primary key.
     *
     * @param int $id The ID of the model.
     * @return array
     *
     * @throws \Nebula\Exceptions\ModelNotFoundException
     */
    public function _findOrFail($id)
    {
        if (empty($this->primaryKey)) {
            throw new \RuntimeException(get_called_class() . " cannot use 'find' because it does not have a primary key.", 500);
        }

        $this->limit = 1;
        $this->clauses = ["{$this->primaryKey} = ?"];
        $this->parameters = [$id];

        $results = $this->get();

        if (empty($results)) {
            throw new ModelNotFoundException("Model cannot be found", 404);
        }

        return $results;
    }

    /**
     * Loads relationships on the current model.
     *
     * @param array $args The comma-separated list of relationships to load.
     * @return \Nebula\Database\Model
     */
    public function _load(...$args)
    {
        $this->_with(...$args);
        $this->handleEagerLoading($this);

        return $this;
    }

    /**
     * Sets the relationships to eager load.
     *
     * @param array $args The comma-separated list of relationships.
     * @return \Nebula\Database\Model
     */
    public function _with(...$args)
    {
        $this->toEagerLoad = $args;

        return $this;
    }

    /**
     * Gets a belongsTo relationship for the model.
     *
     * @param string $className The full name of the model class.
     * @param string $thisKeyName The key for for the current model.
     * @param string $relationKeyName The key for the relation.
     * @return \Nebula\Database\Model
     */
    protected function belongsTo($className, $relationKeyName = '', $thisKeyName = '')
    {
        return $this->handleSimpleRelationship($className, $thisKeyName, $relationKeyName);
    }

    /**
     * Gets a hasOne relationship for the model.
     *
     * @param string $className The full name of the model class.
     * @param string $thisKeyName The key for for the current model.
     * @param string $relationKeyName The key for the relation.
     * @return \Nebula\Database\Model
     */
    protected function hasOne($className, $relationKeyName = '', $thisKeyName = '')
    {
        return $this->handleSimpleRelationship($className, $relationKeyName, $thisKeyName);
    }

    /**
     * Gets a hasMany relationship for the model.
     *
     * @param string $className The full name of the model class.
     * @param string $relationKeyName The key for the relation.
     * @param string $thisKeyName The key for for the current model.
     * @return \Nebula\Collections\Collection
     */
    protected function hasMany($className, $relationKeyName = '', $thisKeyName = '')
    {
        return $this->handleSimpleRelationship($className, $relationKeyName, $thisKeyName, 'many');
    }

    /**
     * Handle all simple relationship queries.
     *
     * @param string $className The full name of the model class.
     * @param string $relationKeyName The key for the relation.
     * @param string $thisKeyName The key for for the current model.
     * @param string $oneOrMany Indicates whether to fetch one or many.
     * @return \Nebula\Database\Model
     */
    protected function handleSimpleRelationship($className, $relationKeyName = '', $thisKeyName = '', $oneOrMany = 'one')
    {
        $calledMethod = debug_backtrace()[2]['function'];

        if (!empty($this->relations[$calledMethod]) && empty($this->executeRelationalQuery)) {
            // If the relationship has already been fetched, the simply return
            return $this->relations[$calledMethod];
        } elseif (!empty($this->executeRelationalQuery)) {
            $this->executeRelationalQuery = false;

            // If the relationship query is to be executed
            // Conditionally add to the model's toSubEagerLoad array for sub queries
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                if (!empty($this->models)) {
                    $this->relations[$calledMethod] = $className::with($this->toSubEagerLoad[$calledMethod])->whereIn($relationKeyName, array_unique($this->models->pluck($thisKeyName)->all()))->get();

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $thisKeyName,
                        'relationKeyName' => $relationKeyName,
                        'oneOrMany' => $oneOrMany
                    ];
                } else {
                    $this->relations[$calledMethod] = $oneOrMany == 'one' ?
                        $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName)->first()
                        : $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName)->get();
                }
            } else {
                if (!empty($this->models)) {
                    $this->relations[$calledMethod] = $className::whereIn($relationKeyName, array_unique($this->models->pluck($thisKeyName)->all()))->get();

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $thisKeyName,
                        'relationKeyName' => $relationKeyName,
                        'oneOrMany' => $oneOrMany
                    ];
                } else {
                    $this->relations[$calledMethod] = $oneOrMany == 'one' ?
                        $className::where($relationKeyName, $this->$thisKeyName)->first()
                        : $className::where($relationKeyName, $this->$thisKeyName)->get();
                }
            }

            return $this->relations[$calledMethod];
        } else {
            // Handle not immediately calling the query
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                $newClass = $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName);
            } else {
                $newClass = $className::where($relationKeyName, $this->$thisKeyName);
            }

            return $newClass;
        }
    }

    /**
     * Gets a belongsToMany relationship for the model.
     *
     * @param string $className The full name of the model class.
     * @param string $pivotTable The name of the pivot table.
     * @param string $relationKeyName The key for the relation.
     * @param string $thisKeyName The key for for the current model.
     * @return \Nebula\Collections\Collection
     */
    protected function belongsToMany($className, $pivotTable, $thisKeyName = '', $relationKeyName = '')
    {
        $calledMethod = debug_backtrace()[1]['function'];
        $this->pivotTableName = $pivotTable;
        $this->pivotThisKeyName = $thisKeyName;
        $this->pivotRelationKeyName = $relationKeyName;

        // Format the relational key name if it does not include a table designation
        if (strpos($relationKeyName, '.') === false) {
            // Get name of model and convert into snake case for table name
            preg_match("/[^\(\\\)]+$/", $className, $matches);
            $relationTable = strtolower($matches[0]) . 's';
            /** TODO: Instantiate model and get the table dynamically??? */
        }

        // Build array of pivot entries to save
        // Get column names from pivot table
        $pivotColNames = DB::raw("SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_SCHEMA = Database()
            AND TABLE_NAME = '$pivotTable'")->get();

        $pivotData = [];
        foreach ($pivotColNames as $col) {
            $pivotData["pivot.{$col->COLUMN_NAME}"] = "$pivotTable.{$col->COLUMN_NAME} as 'pivot.{$col->COLUMN_NAME}'";
        }

        if (!empty($this->relations[$calledMethod]) && !empty($this->executeRelationalQuery)) {
            // If the relationship has already been fetched, the simply return
            return $this->relations[$calledMethod];
        } elseif (!empty($this->executeRelationalQuery) || !empty($this->forceRelationalQuery)) {
            $this->executeRelationalQuery = false;

            // If the relationship query is to be executed
            // Conditionally add to the model's toSubEagerLoad array for sub queries
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                if (!empty($this->models)) {
                    $this->relations[$calledMethod] = $className::with($this->toSubEagerLoad[$calledMethod])
                        ->select("$relationTable.*", implode(',', $pivotData))
                        ->whereIn("$pivotTable.$thisKeyName", array_unique($this->models->pluck($this->primaryKey)->all()))
                        ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName")
                        ->get();

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $thisKeyName,
                        'relationKeyName' => $relationKeyName
                    ];
                } else {
                    $this->relations[$calledMethod] = $className::with($this->toSubEagerLoad[$calledMethod])->select("$relationTable.*", implode(',', $pivotData))->where("$pivotTable.$thisKeyName", $this->{$this->primaryKey})
                        ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName")->get();
                }
            } else {
                if (!empty($this->models)) {
                    $this->relations[$calledMethod] = $className::whereIn("$pivotTable.$thisKeyName", array_unique($this->models->pluck($this->primaryKey)->all()))
                        ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName")
                        ->get();

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $thisKeyName,
                        'relationKeyName' => $relationKeyName
                    ];
                } else {
                    $this->relations[$calledMethod] = $className::select("$relationTable.*", implode(',', $pivotData))->where("$pivotTable.$thisKeyName", $this->{$this->primaryKey})
                        ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName")->get();
                }
            }

            // Remap the pivot data to its own pivot entry
            if (empty($this->relations[$calledMethod])) {
                $this->relations[$calledMethod] = collect([]);
            } else {
                $this->relations[$calledMethod] = collect(array_map(function($item) use ($pivotData) {
                    $pivotArray = [];

                    foreach (array_keys($pivotData) as $key) {
                        $pivotArray[str_replace('pivot.', '', $key)] = $item->{$key};
                        $item->unset($key);
                    }

                    $item->pivot = collect($pivotArray);
                    return $item;
                }, $this->relations[$calledMethod]->all()));
            }

            return $this->relations[$calledMethod];
        } else {
            $syncData = [
                'toSyncKey' => $thisKeyName ?? null,
                'toSyncId' => $this->modelData[$this->primaryKey] ?? null,
                'pivotTableName' => $pivotTable ?? null,
                'pivotThisKeyName' => $thisKeyName ?? null,
                'pivotRelationKeyName' => $relationKeyName ?? null,
                'toSyncModel' => $this ?? null,
                'toSyncCall' => $calledMethod ?? null
            ];

            // Handle not immediately calling the query
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                $class = $className::with($this->toSubEagerLoad[$calledMethod])->select("$relationTable.*", implode(',', $pivotData))->where("$pivotTable.$thisKeyName", $this->{$this->primaryKey})
                    ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName");
            } else {
                $class = $className::select("$relationTable.*", implode(',', $pivotData))->where("$pivotTable.$thisKeyName", $this->{$this->primaryKey})
                    ->join($pivotTable, "$relationTable.{$this->primaryKey}", '=', "$pivotTable.$relationKeyName");
            }

            $class->syncData = $syncData;
            $class->pivotTableName = $pivotTable;
            $class->pivotThisKeyName = $thisKeyName;
            $class->pivotRelationKeyName = $relationKeyName;

            return $class;
        }
    }

    /**
     * Gets a single polymorphic relationship.
     *
     * @return mixed
     */
    protected function morphTo()
    {
        $calledMethod = debug_backtrace()[1]['function'];

        if (!empty($this->relations[$calledMethod]) && empty($this->executeRelationalQuery)) {
            // If the relationship has already been fetched, the simply return
            return $this->relations[$calledMethod];
        } elseif (!empty($this->executeRelationalQuery)) {
            $this->executeRelationalQuery = false;

            // If the relationship query is to be executed
            // Conditionally add to the model's toSubEagerLoad array for sub queries
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                if (!empty($this->models)) {
                    // Dynamically fetch various mapped classes
                    $toFetch = [];
                    $morphColName = "{$calledMethod}_class";
                    $morphIdName = "{$calledMethod}_id";
                    foreach ($this->models as $model) {
                        if (!array_key_exists($model->$morphColName, $toFetch)) {
                            $toFetch[$model->$morphColName] = ['ids' => [], 'results' => []];
                        }

                        $toFetch[$model->$morphColName]['ids'][] = $model->$morphIdName;
                    }

                    $fetchResults = [];
                    foreach ($toFetch as $key => $value) {
                        $fetchResults = array_merge($fetchResults, $key::whereIn('id', $value['ids'])->get()->all());
                    }

                    $this->relations[$calledMethod] = collect($fetchResults);

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $morphIdName,
                        'relationKeyName' => 'id',
                        'oneOrMany' => 'one'
                    ];
                } else {
                    $this->relations[$calledMethod] = $oneOrMany == 'one' ?
                        $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName)->first()
                        : $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName)->get();
                }
            } else {
                if (!empty($this->models)) {
                    // Dynamically fetch various mapped classes
                    $toFetch = [];
                    $morphColName = "{$calledMethod}_class";
                    $morphIdName = "{$calledMethod}_id";
                    foreach ($this->models as $model) {
                        if (!array_key_exists($model->$morphColName, $toFetch)) {
                            $toFetch[$model->$morphColName] = ['ids' => [], 'results' => []];
                        }

                        $toFetch[$model->$morphColName]['ids'][] = $model->$morphIdName;
                    }

                    $fetchResults = [];
                    foreach ($toFetch as $key => $value) {
                        $fetchResults = array_merge($fetchResults, $key::whereIn('id', $value['ids'])->get()->all());
                    }

                    $this->relations[$calledMethod] = collect($fetchResults);

                    return [
                        'results' => $this->relations[$calledMethod],
                        'thisKeyName' => $morphIdName,
                        'relationKeyName' => 'id'
                    ];
                } else {
                    $morphColName = "{$calledMethod}_class";
                    $morphIdName = "{$calledMethod}_id";
                    $this->relations[$calledMethod] = $this->$morphColName::where('id', $this->$morphIdName)->first();
                }
            }

            return $this->relations[$calledMethod];
        } else {
            // Handle not immediately calling the query
            if (!empty($this->toSubEagerLoad[$calledMethod])) {
                $newClass = $className::with($this->toSubEagerLoad[$calledMethod])->where($relationKeyName, $this->$thisKeyName);
            } else {
                $newClass = $className::where($relationKeyName, $this->$thisKeyName);
            }

            return $newClass;
        }
    }

    /**
     * Handles eager loading of model relationships.
     *
     * @param \Nebula\Collections\Collection|\Nebula\Database\Model $object The incoming object.
     * @return \Nebula\Collections\Collection|\Nebula\Database\Model
     */
    protected function handleEagerLoading($object)
    {
        if (empty($object)) {
            return null;
        }

        // Handle collection of objects
        if (get_class($object) == Collection::class) {
            if ($object->isEmpty()) {
                return null;
            }

            foreach ($this->toEagerLoad as $relationship) {
                // Explode the $relationship on '.'
                $relationshipsToLoad = explode('.', $relationship);
                $explodedEagerLoad = explode(',', $relationshipsToLoad[0]);

                foreach ($explodedEagerLoad as $currentEagerLoad) {
                    static::$instance = $this;

                    if (!method_exists($this, $currentEagerLoad)) {
                        throw new ModelException("The relationship {$currentEagerLoad} is not defined.", 500);
                    }

                    // If relationships to load is greater than 1, then queue up the sub with query
                    if (!empty($relationshipsToLoad) && count($relationshipsToLoad) > 1) {
                        array_shift($relationshipsToLoad);
                        $relationshipsToLoad = implode('.', $relationshipsToLoad);
                    } else {
                        $relationshipsToLoad = null;
                    }

                    // Instantiate a blank model class and fetch the results
                    static::$instance->models = $object;

                    if (!empty($relationshipsToLoad)) {
                        $toSubEagerLoad = static::$instance->toSubEagerLoad;
                        $toSubEagerLoad[$currentEagerLoad] = $relationshipsToLoad;
                        static::$instance->toSubEagerLoad = $toSubEagerLoad;
                    }

                    $eagerResults = static::$instance->{$currentEagerLoad};
                    static::$instance->toSubEagerLoad = [];

                    foreach ($object as &$model) {
                        // Loop through models and add relational key to appropriate models
                        if (strpos($currentEagerLoad, 'able') !== false) {
                            $classColName = "{$currentEagerLoad}_class";

                            $model->relations[$currentEagerLoad]
                                = !empty($eagerResults) && !is_null($model->{$eagerResults['thisKeyName']}) ? $eagerResults['results']->where($eagerResults['relationKeyName'], $model->{$eagerResults['thisKeyName']})->whereClass($model->$classColName)->first() : null;
                        } else {
                            $model->relations[$currentEagerLoad] = !empty($eagerResults['oneOrMany']) && $eagerResults['oneOrMany'] == 'one'
                                ? (!empty($eagerResults) && !is_null($model->{$eagerResults['thisKeyName']}) ? ($eagerResults['results']->where($eagerResults['relationKeyName'], $model->{$eagerResults['thisKeyName']})->first()) : null)
                                : (!empty($eagerResults) && !is_null($model->{$eagerResults['thisKeyName']}) ? ($eagerResults['results']->where($eagerResults['relationKeyName'], $model->{$eagerResults['thisKeyName']})) : null);
                        }
                    }
                }
            }
        } else {
            // Assume this is a singular model
            foreach ($this->toEagerLoad as $relationship) {
                // Explode the $relationship on '.'
                $relationshipsToLoad = explode('.', $relationship);
                $currentEagerLoad = $relationshipsToLoad[0];

                if (!method_exists($this, $currentEagerLoad)) {
                    throw new ModelException("The relationship {$currentEagerLoad} is not defined.", 500);
                }

                // If relationships to load is greater than 1, then queue up the sub with query
                if (count($relationshipsToLoad) > 1) {
                    array_shift($relationshipsToLoad);

                    $toSubEagerLoad = $object->toSubEagerLoad;
                    $toSubEagerLoad[$currentEagerLoad] = implode('.', $relationshipsToLoad);
                    $object->toSubEagerLoad = $toSubEagerLoad;
                }

                $object->relations[$currentEagerLoad] = $object->{$currentEagerLoad};
            }
        }

        return $object;
    }

    /**
     * Saves the model data by either updating or inserting.
     *
     * @return \Nebula\Database\Model
     */
    public function save()
    {
        if (empty($this->primaryKey)) {
            throw new ModelException("Cannot save a model directly without a primary key.", 500);
        }

        if (isset($this->modelData[$this->primaryKey])) {
            // Add timestamp updates if applicable
            if (!empty($this->timestamps)) {
                $this->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            // Handle updates
            $result = $this->where($this->primaryKey, $this->modelData[$this->primaryKey])->update($this->changedModelData);
        } else {
            $className = get_called_class();

            // Add timestamp updates if applicable
            if (!empty($this->timestamps)) {
                $this->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $this->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            // Handle insertion
            $results = $this->insert([$this->changedModelData]);
            return new $className($this->dbConnection, ['data' => $results[0]]);
        }

        $this->changedModelData = [];

        return $result;
    }

    /**
     * Syncs a many to many model by syncing pivot table data.
     *
     * @param array $idsToAdd The array of IDs to add.
     * @return void
     */
    public function sync(array $idsToAdd)
    {
        if (empty($this->syncData['toSyncKey'])) {
            throw new ModelException("Cannot call sync when belongsToMany relationship has not been initialized.", 500);
        }

        // If pivot table name includes a '.', explode to get the connection name.
        // Otherwise use the connection on the current model.
        if (strpos($this->pivotTableName, '.') !== false) {
            $pivotData = explode('.', $this->pivotTableName);
        } else {
            $pivotData = [
                $this->connection,
                $this->pivotTableName
            ];
        }

        // First remove all of the existing pivot data
        DB::connection($pivotData[0])->table($pivotData[1])
            ->where($this->syncData['toSyncKey'], $this->syncData['toSyncId'])
            ->delete();

        // Add all of the relationships included in $idToAdd
        if (!empty($idsToAdd)) {
            $dataToAdd = [];

            foreach ($idsToAdd as $id) {
                $dataToAdd[] = [
                    $this->syncData['toSyncKey'] => $this->syncData['toSyncId'],
                    $this->pivotRelationKeyName => $id
                ];
            }

            DB::connection($pivotData[0])->table($pivotData[1])
                ->insert($dataToAdd);

            // Refetch relationship
            $syncModel = $this->syncData['toSyncModel'];
            $syncModel->forceRelationalQuery = true;
            $syncModel->{$this->syncData['toSyncCall']};
            $syncModel->forceRelationalQuery = false;
        }
    }

    /**
     * Attaches a model relationship to an existing model.
     *
     * @param int $idToAttach The ID of the model to attach to parent model.
     * @param array $pivotDataToAdd The optional list of pivot data to include during the attach.
     * @return void
     */
    public function attach($id, $pivotDataToAdd = [])
    {
        if (empty($this->syncData['toSyncKey'])) {
            throw new ModelException("Cannot call attach when hasMany relationship has not been initialized.", 500);
        }

        // If pivot table name includes a '.', explode to get the connection name.
        // Otherwise use the connection on the current model.
        if (strpos($this->pivotTableName, '.') !== false) {
            $pivotData = explode('.', $this->pivotTableName);
        } else {
            $pivotData = [
                $this->connection,
                $this->pivotTableName
            ];
        }

        $dataToAdd = [];

        $dataToAdd[] = [
            $this->syncData['toSyncKey'] => $this->syncData['toSyncId'],
            $this->pivotRelationKeyName => $id
        ];

        $dataToAdd = array_merge($dataToAdd, $pivotDataToAdd);

        DB::connection($pivotData[0])->table($pivotData[1])
            ->insert($dataToAdd);

        // Refetch relationship
        $syncModel = $this->syncData['toSyncModel'];
        $syncModel->forceRelationalQuery = true;
        $syncModel->{$this->syncData['toSyncCall']};
        $syncModel->forceRelationalQuery = false;
    }

    /**
     * Detaches a model relationship to an existing model.
     *
     * @param int $idToAttach The ID of the model to attach to parent model.
     * @return void
     */
    public function detach($id)
    {
        if (empty($this->syncData['toSyncKey'])) {
            throw new ModelException("Cannot call detach when hasMany relationship has not been initialized.", 500);
        }

        // If pivot table name includes a '.', explode to get the connection name.
        // Otherwise use the connection on the current model.
        if (strpos($this->pivotTableName, '.') !== false) {
            $pivotData = explode('.', $this->pivotTableName);
        } else {
            $pivotData = [
                $this->connection,
                $this->pivotTableName
            ];
        }

        DB::connection($pivotData[0])->table($pivotData[1])
            ->where($this->pivotRelationKeyName, $id)->delete();

        // Refetch relationship
        $syncModel = $this->syncData['toSyncModel'];
        $syncModel->forceRelationalQuery = true;
        $syncModel->{$this->syncData['toSyncCall']};
        $syncModel->forceRelationalQuery = false;
    }

    /**
     * Creates a new model using array data.
     *
     * @param array $newModelData The array containing new model data.
     * @return \Nebula\Database\Model
     */
    public function _create($newModelData)
    {
        try {
            $result = $this->insert($newModelData);
        } catch (ModelException $e) {
            /** Throw Model Exception? */
        }

        $model = new $this($this->dbConnection, ['data' => $result[0]]);

        // Add timestamp updates if applicable
        if (!empty($model->timestamps)) {
            $model->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $model->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $model->save();
        }

        return $model;
    }

    /**
     * Parse the results of a query.
     *
     * @param array $results The query results as an array.
     * @return array
     */
    protected function parseResults($results)
    {
        $data = collect();

        if (!empty($results)) {
            $className = get_called_class();

            // Handle multiple data sets
            if (!empty($results[0]) && is_array($results[0])) {
                $data = array_map(function($m) use ($className) {
                    $class = new $className($this->dbConnection, ['data' => $m]);

                    return $class;
                }, $results);

                // Create a collection of the results
                $data = collect($data);
            } else {
                if ($results != 'nullSingle') {
                    $data = new $className($this->dbConnection, ['data' => $results]);
                } elseif ($results == 'nullSingle') {
                    $data = null;
                }
            }
        }

        return $data;
    }

    /**
     * Resets the static model attributes.
     *
     * @return void
     */
    protected function resetStaticModel()
    {
        $this->toSubEagerLoad = [];
        $this->toEagerLoad = [];
        $this->pivotTableName = '';
        $this->pivotThisKeyName = '';
        $this->pivotRelationKeyName = '';
    }

    /**
     * Cleans certain attributes from the model.
     *
     * @return \Nebula\Database\Model
     */
    public function clean()
    {
        foreach ($this->hidden as $hiddenAttr) {
            if (array_key_exists($hiddenAttr, $this->modelData)) {
                unset($this->modelData[$hiddenAttr]);
            } elseif (strpos($hiddenAttr, 'pivot.') !== false) {
                $hiddenAttr = str_replace('pivot.', '', $hiddenAttr);

                if (isset($this->modelData['pivot'][$hiddenAttr])) {
                    unset($this->modelData['pivot'][$hiddenAttr]);
                }
            }
        }

        // Handle nested relational cleansing
        foreach ($this->relations as $relation) {
            if (!empty($relation)) {
                if (is_array($relation)) {
                    foreach ($relation as $subRelation) {
                        $subRelation->clean();
                    }
                } elseif (is_object($relation)) {
                    $relation->clean();
                }
            }
        }

        return $this;
    }
}
