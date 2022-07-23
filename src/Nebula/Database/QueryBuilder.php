<?php

namespace Nebula\Database;

use Nebula\Database\DatabaseManager;
use PDO;
use PDOException;

class QueryBuilder {
    /**
     * The PDO connection instance.
     *
     * @var \PDO
     */
    protected $dbConnection;

    /**
     * The database name for the query.
     *
     * @var string
     */
    protected $connection;

    /**
     * The database table for the query.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the table.
     *
     * @var int
     */
    protected $primaryKey = null;

    /**
     * The list of selects for the active query.
     *
     * @var array
     */
    protected $selects = [];

    /**
     * The list of clauses for the active query.
     *
     * @var array
     */
    protected $clauses = [];

    /**
     * The list of parameters for the active query.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The list of joins for the active query.
     *
     * @var array
     */
    protected $joins = [];

    /**
     * The list of updates for the active query.
     *
     * @var array
     */
    protected $updates = [];

    /**
     * The list of inserts for the active query.
     *
     * @var array
     */
    protected $inserts = [];

    /**
     * The list of inserted IDs.
     *
     * @var array
     */
    protected $insertedIds = [];

    /**
     * The active query string.
     *
     * @var string
     */
    protected $query = '';

    /**
     * The limit for the active query.
     *
     * @var int|null
     */
    protected $limit = null;

    /**
     * The order by direction for the active query.
     *
     * @var string|null
     */
    protected $orderByDirection = 'asc';

    /**
     * The order by key for the active query.
     *
     * @var string|null
     */
    protected $orderByKey = null;

    /**
     * Indicates whether to order by rand().
     *
     * @var bool
     */
    protected $orderByRand = false;

    /**
     * The list of evaluations to check for.
     *
     * @var array
     */
    private $evaluations = ['>', '<', '=', '<=', '>=', '!=', '<>', 'like'];

    /**
     * Indicates whether the active query is a group.
     *
     * @var bool
     */
    protected $isGroup = false;

    /**
     * Indicates whether the active clase is the first of a group.
     *
     * @var bool
     */
    protected $isFirstOfGroup = false;

    /**
     * Indicates whether the active query is a delete.
     *
     * @var bool
     */
    protected $delete = false;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(PDO $dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Handle when method call is executed when method doesn't exist.
     *
     * @param string $method The name of the method.
     * @param array $args The array of data for the method.
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->call($method, $args);
    }

    /**
     * Handle statically called method call when method doesn't exist.
     *
     * @param string $method The name of the method.
     * @param array $args The array of data for the method.
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return (new static())->call($method, $args);
    }

    /**
     * Handle method call from __call and __callStatic.
     *
     * @param string $method The name of the method.
     * @param array $args The array of data for the method.
     * @return mixed
     *
     * @throws \Exception
     */
    private function call($method, $args)
    {
        if (!method_exists($this , '_' . $method)) {
            throw new \Exception('Call undefined method ' . $method, 500);
        }

        // Don't allow access to underscored methods if model data exists.
        // This prevents models from trying to refetch data with a query.
        if (!empty($this->modelData) && !in_array($method, ['insert', 'update', 'where', 'toSql', 'first', 'load'])) {
            throw new \Exception('Instance of ' . get_called_class() . ' cannot call the method: ' . $method, 500);
        }

        return $this->{'_' . $method}(...$args);
    }

    /**
     * Returns the PDO instance.
     *
     * @return \PDO
     */
    public function _getPdo()
    {
        return $this->dbConnection;
    }

    /**
     * Sets the connection/database name for the active query.
     *
     * @param string $connection The database name.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _connection(string $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Sets the table name for the active query.
     *
     * @param string $table The name of the table.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _table(string $table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Adds select definitions to the active query.
     *
     * @param array $args The arguments for selects as an array of strings.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _select(...$args)
    {
        foreach ($args as $selectKey) {
            $this->selects[] = $selectKey;
        }

        return $this;
    }

    /**
     * Adds where clauses to active query.
     *
     * @param mixed $args
     * @return \Nebula\Database\QueryBuilder
     */
    public function _where(...$args)
    {
        $parameters = [];
        $clause = '';

        if (empty($args)) {
            throw new \Exception("QueryBuilder 'where' clause requires arguments.", 500);
        }

        // Handle arrays
        if (is_array($args[0])) {
            if (is_array($args[0][0])) {
                $clause = "(";
                $whereClauses = [];

                foreach ($args[0] as $whereArray) {
                    $results = $this->parseWhere($whereArray);
                    $whereClauses[] = $results['clause'];
                    $parameters[] = $results['parameter'];
                }

                $clause .= implode(" AND ", $whereClauses) . ")";
            } else {
                $results = $this->parseWhere($args[0]);
                $clause = $results['clause'];
                $parameters[] = $results['parameter'];
            }
        } elseif (is_callable($args[0])) {
            $this->isGroup = true;
            $this->isFirstOfGroup = true;
            $args[0]($this);
            $this->isGroup = false;

            return $this;
        } else {
            if (!empty($this->isGroup)) {
                if (empty($this->isFirstOfGroup)) {
                    // Remove the last element from the clause array
                    $clause = $this->popLastElement();
                }

                $results = $this->parseWhere($args);
                $clause = "(" . $clause . $results['clause'] . ")";

                if (!empty($this->isFirstOfGroup)) {
                    $this->isFirstOfGroup = false;
                }
            } else {
                $results = $this->parseWhere($args);
                $clause = $results['clause'];
            }

            $parameters[] = $results['parameter'];
        }

        $this->clauses[] = $clause;
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Adds or where clauses to active query.
     *
     * @param mixed $args
     * @return \Nebula\Database\QueryBuilder
     */
    public function _orWhere(...$args)
    {
        $parameters = [];
        $clause = '';

        if (is_callable($args[0])) {
            $this->isGroup = true;
            $this->isFirstOfGroup = true;
            $args[0]($this);

            // Remove the last element from the clause array to ensure OR keyword
            $clause = array_pop($this->clauses);
            $clause = preg_replace("@\((?!\?)@", "OR (", $clause);

            $this->clauses[] = $clause;
            $this->isGroup = false;

            return $this;
        } else {
            if (!empty($this->isGroup)) {
                if (empty($this->isFirstOfGroup)) {
                    // Remove the last element from the clause array
                    $clause = $this->popLastElement();
                }

                $results = $this->parseWhere($args, 'OR');
                $clause = "(" . $clause . $results['clause'] . ")";

                if (!empty($this->isFirstOfGroup)) {
                    $this->isFirstOfGroup = false;
                }
            } else {
                $results = $this->parseWhere($args, 'OR');
                $clause = $results['clause'];
            }

            $parameters[] = $results['parameter'];
        }

        $this->clauses[] = $clause;
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Adds a where is null claus to active query.
     *
     * @param string $attribute The attribute to test for null.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereNull($attribute)
    {
        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute IS NULL" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute IS NULL";
        }

        $this->clauses[] = $clause;

        return $this;
    }

    /**
     * Adds a or where is null claus to active query.
     *
     * @param string $attribute The attribute to test for null.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _orWhereNull($attribute)
    {
        $clause = '';

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "("
                . $clause
                . (empty($this->isFirstOfGroup) ? "OR " : "")
                . "$attribute IS NULL"
                . ")"
            ;

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "OR $attribute IS NULL";
        }

        $this->clauses[] = $clause;

        return $this;
    }

    /**
     * Adds a where is not null claus to active query.
     *
     * @param string $attribute The attribute to test for null.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereNotNull($attribute)
    {
        $clause = '';

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute IS NOT NULL" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute IS NOT NULL";
        }

        $this->clauses[] = $clause;

        return $this;
    }

    /**
     * Adds a or where is null claus to active query.
     *
     * @param string $attribute The attribute to test for null.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _orWhereNotNull($attribute)
    {
        $clause = '';

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "("
                . $clause
                . (empty($this->isFirstOfGroup) ? "OR " : "")
                . "$attribute IS NOT NULL"
                . ")"
            ;

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "OR $attribute IS NOT NULL";
        }

        $this->clauses[] = $clause;

        return $this;
    }

    /**
     * Adds a where date clause to active query.
     *
     * @param string $attribute The attribute to test for date.
     * @param string $date The date for the clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereDate($attribute, $date)
    {
        $clause = '';

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "date($attribute) = ?" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "date($attribute) = ?";
        }

        $this->clauses[] = $clause;
        $this->parameters[] = $date;

        return $this;
    }

    /**
     * Adds a where year clause to active query.
     *
     * @param string $attribute The attribute to test for year.
     * @param string $year The year for the clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereYear($attribute, $year)
    {
        $clause = '';

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "year($attribute) = ?" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "year($attribute) = ?";
        }

        $this->clauses[] = $clause;
        $this->parameters[] = $year;

        return $this;
    }

    /**
     * Adds a where between clause to active query.
     *
     * @param string $attribute The attribute to test for between.
     * @param array $conditions The conditions for the between clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereBetween($attribute, array $conditions)
    {
        $clause = '';

        if (count($conditions) != 2) {
            throw new \RuntimeException("There can only be 2 conditions when using whereBetween.", 500);
        }

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute BETWEEN ? AND ?" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute BETWEEN ? AND ?";
        }

        $this->clauses[] = $clause;
        $this->parameters[] = $conditions[0];
        $this->parameters[] = $conditions[1];

        return $this;
    }

    /**
     * Adds a where not between clause to active query.
     *
     * @param string $attribute The attribute to test for between.
     * @param array $conditions The conditions for the between clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereNotBetween($attribute, array $conditions)
    {
        $clause = '';

        if (count($conditions) != 2) {
            throw new \RuntimeException("There can only be 2 conditions when using whereNotBetween.", 500);
        }

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute NOT BETWEEN ? AND ?" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute NOT BETWEEN ? AND ?";
        }

        $this->clauses[] = $clause;
        $this->parameters[] = $conditions[0];
        $this->parameters[] = $conditions[1];

        return $this;
    }

    /**
     * Adds a where in clause to active query.
     *
     * @param string $attribute The attribute to test for in.
     * @param array $conditions The conditions for the in clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereIn($attribute, array $conditions)
    {
        $clause = '';

        // Build prepared placeholders
        $placeholder = $this->buildPreparedPlaceholder($conditions);

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute IN ($placeholder)" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute IN ($placeholder)";
        }

        $this->clauses[] = $clause;
        $this->parameters = array_merge($this->parameters, $conditions);

        return $this;
    }

    /**
     * Adds a where not in clause to active query.
     *
     * @param string $attribute The attribute to test for in.
     * @param array $conditions The conditions for the in clause.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _whereNotIn($attribute, array $conditions)
    {
        $clause = '';

        // Build prepared placeholders
        $placeholder = $this->buildPreparedPlaceholder($conditions);

        if (!empty($this->isGroup)) {
            if (empty($this->isFirstOfGroup)) {
                // Remove the last element from the clause array
                $clause = $this->popLastElement();
            }

            $clause = "(" . $clause . "$attribute NOT IN ($placeholder)" . ")";

            if (!empty($this->isFirstOfGroup)) {
                $this->isFirstOfGroup = false;
            }
        } else {
            $clause = "$attribute NOT IN ($placeholder)";
        }

        $this->clauses[] = $clause;
        $this->parameters = array_merge($this->parameters, $conditions);

        return $this;
    }

    /**
     * Adds a raw database query to the active query.
     *
     * @param string $query The query string.
     * @param array $parameters The optional parameters for the query.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _raw($query, $parameters = [])
    {
        $this->query = $query;
        $this->parameters = $parameters;
        $this->clauses = [];

        return $this;
    }

    /**
     * Adds an inner join to active query.
     *
     * @param string $table The name of the table to join.
     * @param string $keyOne The first identifier for the join.
     * @param string $evaluation The evaluation of the join.
     * @param string $keyTwo The second identifier for the join.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _join($table, $keyOne, $evaluation, $keyTwo)
    {
        if ($evaluation != '=') {
            throw new \RuntimeException(
                "Evaluation must be '=' while using an inner join.",
                500
            );
        }

        $this->joins[] = "INNER JOIN $table ON $keyOne $evaluation $keyTwo";

        return $this;
    }

    /**
     * Adds a left join to active query.
     *
     * @param string $table The name of the table to join.
     * @param string $keyOne The first identifier for the join.
     * @param string $evaluation The evaluation of the join.
     * @param string $keyTwo The second identifier for the join.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _leftJoin($table, $keyOne, $evaluation, $keyTwo)
    {
        if ($evaluation != '=') {
            throw new \RuntimeException(
                "Evaluation must be '=' while using an inner join.",
                500
            );
        }

        $this->joins[] = "LEFT JOIN $table ON $keyOne $evaluation $keyTwo";

        return $this;
    }

    /**
     * Adds a right join to active query.
     *
     * @param string $table The name of the table to join.
     * @param string $keyOne The first identifier for the join.
     * @param string $evaluation The evaluation of the join.
     * @param string $keyTwo The second identifier for the join.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _rightJoin($table, $keyOne, $evaluation, $keyTwo)
    {
        if ($evaluation != '=') {
            throw new \RuntimeException(
                "Evaluation must be '=' while using an inner join.",
                500
            );
        }

        $this->joins[] = "RIGHT JOIN $table ON $keyOne $evaluation $keyTwo";

        return $this;
    }

    /**
     * Adds an order by clause to the active query.
     *
     * @param string $key The key to order the results.
     * @param string $direction The direction for sorting.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _orderBy($key, $direction = 'asc')
    {
        if (!in_array($direction, ['asc', 'desc'])) {
            throw new \Exception("Order by accepts only 'asc' and 'desc' as directional arguments.", 500);
        }

        $this->orderByDirection = $direction;
        $this->orderByKey = $key;

        return $this;
    }

    /**
     * Adds an order by rand clause to the active query.
     *
     * @return \Nebula\Database\QueryBuilder
     */
    public function _orderByRand()
    {
        $this->orderByRand = true;

        return $this;
    }

    /**
     * Sets the limit clause on the active query.
     *
     * @param int $limit The amount of data to fetch.
     * @return array
     *
     * @throws \RuntimeException
     */
    public function _limit($limit = 1)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Returns an avg aggregate.
     *
     * @param string $column The name of the column to aggregate.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _avg($column)
    {
        return $this->setAggregate(__FUNCTION__, $column)[0]['aggregate'] ?? null;
    }

    /**
     * Returns a max aggregate.
     *
     * @param string $column The name of the column to aggregate.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _max($column)
    {
        return $this->setAggregate(__FUNCTION__, $column)[0]['aggregate'] ?? null;
    }

    /**
     * Returns a min aggregate.
     *
     * @param string $column The name of the column to aggregate.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _min($column)
    {
        return $this->setAggregate(__FUNCTION__, $column)[0]['aggregate'] ?? null;
    }

    /**
     * Returns a sum aggregate.
     *
     * @param string $column The name of the column to aggregate.
     * @return \Nebula\Database\QueryBuilder
     */
    public function _sum($column)
    {
        return $this->setAggregate(__FUNCTION__, $column)[0]['aggregate'] ?? 0;
    }

    /**
     * Sets and executes a deletion based on the query builder results.
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function _delete()
    {
        $this->delete = true;

        return $this->_get();
    }

    /**
     * Gets the first result of the active query.
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function _first()
    {
        $this->limit = 1;

        return $this->_get('first');
    }

    /**
     * Gets the results of the active query.
     *
     * @param mixed $type Indicates the type. Mainly used to determine if empty should be collection or null.
     * @return array
     *
     * @throws \RuntimeException
     */
    public function _get($type = null)
    {
        // Build the active query string. Only build if query is empty (see $this->raw(...)).
        if (empty($this->query)) {
            $this->buildQuery();
        }

        // Execute the query and save the results
        $results = $this->executeQuery();

        // Reset the active query builder
        $this->resetQueryBuilder();

        if (empty($this->delete)) {
            // Parse the results
            $parsedResults = $this->parseResults($results);

            // Handle eager loading callback if present
            if (method_exists($this, 'handleEagerLoading')) {
                $parsedResults = $this->handleEagerLoading($parsedResults);
            }

            return $parsedResults ?? ($type == 'first' ? null : collect([]));
        } else {
            $this->delete = false;
            return true;
        }
    }

    /**
     * Returns the active query as a SQL string.
     *
     * @param bool $reset Indicates whether to reset the query.
     * @return string
     */
    public function _toSql($reset = false)
    {
        // Build the active query string. Only build if query is empty (see $this->raw(...)).
        if (empty($this->query)) {
            $this->buildQuery();
        }

        $query = $this->query;

        // Reset the active query builder
        if ($reset) {
            $this->resetQueryBuilder();
        }

        return $query;
    }

    /**
     * Add insert data to active query and executes.
     *
     * @param array $data The data array for the insert.
     * @return array
     */
    public function _insert(array $data)
    {
        // Get the primary key for the table if not already defined
        $this->primaryKey = $this->primaryKey ?? $this->getPrimaryKey();

        $i = 0;
        foreach ($data as $insertKey => $insertValue) {
            // Handle multiple inserts
            if (is_array($insertValue)) {
                foreach ($insertValue as $key => $value) {
                    if (empty($this->inserts[$i])) {
                        $this->inserts[$i] = [];
                    }

                    $this->inserts[$i][$key] = $value;

                    $this->parameters[$i][] = $value;
                }
            } else {
                // Handle single inserts
                if (empty($this->inserts[0])) {
                    $this->inserts[0] = [];
                }

                $this->inserts[0][$insertKey] = $insertValue;

                $this->parameters[0][] = $insertValue;
            }

            $i++;
        }

        $this->buildQuery();

        // Execute the query and save the results
        $results = $this->executeQuery();

        if ($results && !empty($this->insertedIds)) {
            try {
                $results = $this->dbConnection->query(
                    "SELECT * FROM {$this->connection}.{$this->table}"
                        . " WHERE {$this->primaryKey} IN (" . implode(',', $this->insertedIds) . ")"
                )->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage, 500, $e);
            }
        }

        // Reset the active query builder
        $this->resetQueryBuilder();

        return $results ?? [];
    }

    /**
     * Adds update clause to active query and executes.
     *
     * @param array $data The data array for the update.
     * @return array
     */
    public function _update(array $data)
    {
        // Get the primary key for the table if not already defined
        $this->primaryKey = $this->primaryKey ?? $this->getPrimaryKey();

        $newParams = [];
        foreach ($data as $key => $value) {
            $this->updates[] = "$key = ?";
            $newParams[] = $value;
        }

        $this->parameters = array_merge($newParams, $this->parameters);

        $this->clauses[] = "(SELECT @uids := CONCAT_WS(',', {$this->primaryKey}, @uids))";

        $this->buildQuery();

        $this->dbConnection->query("SET @uids := 0")->execute();

        // Execute the query and save the results
        $results = $this->executeQuery();

        if ($results) {
            if (!empty($this->primaryKey)) {
                $results = $this->dbConnection->query("SELECT @uids")->fetchAll();

                $results = $this->dbConnection->query(
                    "SELECT {$this->primaryKey} FROM {$this->connection}.{$this->table}"
                        . " WHERE {$this->primaryKey} IN ({$results[0]['@uids']})"
                )->fetchAll(\PDO::FETCH_ASSOC);
            }
        }

        // Reset the active query builder
        $this->resetQueryBuilder();

        return $results ?? [];
    }

    /**
     * Pops the last element from a clause group and returns the formatted version.
     *
     * @return string
     */
    private function popLastElement()
    {
        $clause = array_pop($this->clauses);
        $clause = preg_replace("@(?<!\,\?)\)@", " AND ", $clause);
        $clause = preg_replace("@\((?!\?)@", "", $clause);

        return $clause;
    }

    /**
     * Builds a prepared placeholder for number of elements in an array.
     *
     * @param array $conditions The conditions for the placeholder.
     * @return string
     */
    private function buildPreparedPlaceholder(array $conditions)
    {
        $placeholder = '';
        for ($i=0; $i < count($conditions); $i++) {
            if ($i > 0) {
                $placeholder .= ",";
            }

            $placeholder .= "?";
        }

        return $placeholder;
    }

    /**
     * Sets the aggregate for the query. This will override the current selects on the class.
     *
     * @param string $function The name of the SQL function.
     * @param string $column The name of the column to aggreate.
     * @return array
     */
    private function setAggregate($function, $column)
    {
        // Remove leading underscore
        $function = ltrim($function, '_');

        $this->selects = [
            "$function($column) as aggregate"
        ];

        return $this->get();
    }

    /**
     * Parses where clause arguments.
     *
     * @param array $args The array arguments.
     * @param string $connector The optional connector word for where clause (OR, etc.).
     * @return array
     */
    private function parseWhere($args, $connector = '')
    {
        $clause = '';
        $parameter = '';

        if (!isset($args[1]) && !is_null($args[1])) {
            throw new \RuntimeException(
                "Second argument is required when using a standard where clause.",
                500
            );
        }

        $clause = (!empty($connector) && empty($this->isFirstOfGroup) ? "$connector " : '') . "$args[0]";

        // Check to see if second argument is an evaluation
        // and handle appropriately.
        if (in_array(strtolower($args[1]), $this->evaluations)) {
            if (!isset($args[2])) {
                throw new \RuntimeException(
                    "Third argument is required when using an evaluation in a where clause.",
                    500
                );
            }

            $clause .= " {$args[1]} ?";
            $parameter = $args[2];
        } else {
            $clause .= " = ?";
            $parameter = $args[1];
        }

        return [
            'clause' => $clause,
            'parameter' => $parameter
        ];
    }

    /**
     * Builds the active query string.
     *
     * @return string
     */
    private function buildQuery()
    {
        if (!empty($this->updates)) {
            $this->query = "UPDATE {$this->connection}.{$this->table}" . " SET " .  implode(',', $this->updates);
        } elseif (!empty($this->inserts)) {
            // Convert class query to array for inserts
            $this->query = [];

            foreach ($this->inserts as $insert) {
                $baseQuery = "INSERT INTO {$this->connection}.{$this->table}" . " (" .  implode(',', array_keys($insert)) . ") VALUES ";

                $insertPlaceholders = [];
                $placeholderMarks = [];
                for ($i=0; $i < count($insert); $i++) {
                    $placeholderMarks[] = "?";
                }

                $insertPlaceholders[] = "(" . implode(',', $placeholderMarks) . ")";

                $this->query[] = $baseQuery . implode(',', $insertPlaceholders);
            }
        } elseif (!empty($this->delete)) {
            $this->query = "DELETE FROM {$this->connection}.{$this->table}";
        } else {
            $this->query = "SELECT " . (empty($this->selects) ? '*' : implode(',', $this->selects))
                . " FROM {$this->connection}.{$this->table}";
        }

        if (empty($this->inserts)) {
            if (!empty($this->joins)) {
                $this->query .= " " . implode(" ", $this->joins);
            }

            if (!empty($this->clauses)) {
                $this->query .= " WHERE " . implode(" AND ", $this->clauses);
            }

            if (!empty($this->orderByRand)) {
                $this->query .= " ORDER BY RAND()";
            } elseif (!empty($this->orderByKey)) {
                $this->query .= " ORDER BY {$this->orderByKey} {$this->orderByDirection}";
            }

            if (!empty($this->limit)) {
                $this->query .= " LIMIT {$this->limit}";
            }
        }

        // Correct OR instances
        $this->query = str_replace('AND OR', 'OR', $this->query);
    }

    /**
     * Executes the active query string and returns the results.
     *
     * @return array
     */
    private function executeQuery($scope = 'all')
    {
        if ($this->limit == 1) {
            $scope = 'one';
        }

        // $isDebugMode = config('database.debug') ?? false;
        try {
            if (!empty($this->inserts)) {
                $i = 0;
                foreach ($this->query as $q) {
                    // if ($isDebugMode == true) {
                    //     \Nebula\Accessors\Log::channel('debug')->info($query);
                    //     $this->dbConnection->query('set profiling=1');
                    // }

                    $query = $this->dbConnection->prepare($q);

                    try {
                        $results = $query->execute($this->parameters[$i]);

                        // Fetch the last inserted ID
                        $this->insertedIds[] = $this->dbConnection->query("
                            SELECT {$this->primaryKey} FROM {$this->connection}.{$this->table}
                                ORDER BY {$this->primaryKey} DESC LIMIT 1
                        ")->fetch()[$this->primaryKey] ?? null;
                    } catch (\Exception $e) {
                        throw new \PDOException($e->getMessage(), 500, $e);
                    }

                    // if ($isDebugMode == true) {
                    //     $res = $this->dbConnection->query('show profiles');
                    //     $records = $res->fetchAll(PDO::FETCH_ASSOC);
                    //     \Nebula\Accessors\Log::channel('debug')->info("Execution Time: " . $records[0]['Duration']*1000 . "ms");
                    //     $this->dbConnection->query('set profiling=0');
                    // }

                    $i++;
                }
            } else {
                // if ($isDebugMode == true) {
                //     \Nebula\Accessors\Log::channel('debug')->info($this->query);
                //     $this->dbConnection->query('set profiling=1');
                // }

                $query = $this->dbConnection->prepare($this->query);

                if (!empty($this->updates) || !empty($this->delete)) {
                    $results = $query->execute($this->parameters);
                } else {
                    if ($query->execute($this->parameters)) {
                        if ($scope == 'all') {
                            $results = $query->fetchAll(\PDO::FETCH_ASSOC);
                        } else {
                            $results = $query->fetch(\PDO::FETCH_ASSOC);
                            $results = $results === false ? 'nullSingle' : $results;
                        }
                    }
                }

                // if ($isDebugMode == true) {
                //     $res = $this->dbConnection->query('show profiles');
                //     $records = $res->fetchAll(PDO::FETCH_ASSOC);
                //     \Nebula\Accessors\Log::channel('debug')->info("Execution Time: " . $records[0]['Duration']*1000 . "ms");
                //     $this->dbConnection->query('set profiling=0');
                // }
            }
        } catch (\Exception $e) {
            throw new \PDOException($e->getMessage(), 500, $e);
        }

        return $results;
    }

    /**
     * Gets the primary key for a database/table combination.
     *
     * @return string
     */
    private function getPrimaryKey()
    {
        try {
            $column = $this->dbConnection->query("SHOW INDEXES
                FROM {$this->connection}.{$this->table}
                WHERE Key_name = 'PRIMARY'")->fetch();
        } catch (\Exception $e) {
            throw new \PDOException($e->getMessage(), 500);
        }

        return $column['Column_name'] ?? null;
    }

    /**
     * Parse the results of a query.
     *
     * @param array $results The query results as an array.
     * @return array
     */
    protected function parseResults($results)
    {
        if (!empty($results)) {
            // Handle multiple data sets
            if (!empty($results[0]) && is_array($results[0])) {
                $data = array_map(function($m) {
                    return (object) $m;
                }, $results);

                // Create a collection of the results
                $data = collect($data);
            } else {
                $data = (object) $results;
            }
        }

        return $data ?? collect([]);
    }

    /**
     * Resets the query builder to the original state.
     *
     * @return void
     */
    private function resetQueryBuilder()
    {
        $this->selects = [];
        $this->joins = [];
        $this->clauses = [];
        $this->parameters = [];
        $this->updates = [];
        $this->inserts = [];
        $this->insertedIds = [];
        $this->query = '';
        $this->limit = null;
        $this->isGroup = false;
        $this->isFirstOfGroup = false;
        $this->orderByDirection = null;
        $this->orderByKey = null;
        $this->orderByRand = false;
    }
}
