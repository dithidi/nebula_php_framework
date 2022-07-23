<?php

namespace Nebula\Accessors;

use Nebula\Accessors\Accessor;
use Nebula\Database\QueryBuilder;

class DB extends Accessor {
    protected static function defineClass()
    {
        return QueryBuilder::class;
    }
}
