<?php

namespace Nebula\Console;

use Symfony\Component\VarDumper\Caster\Caster;

class SynthesizeCaster {
    /**
     * Get an array representing the properties of a collection.
     *
     * @param  \Nebula\Collections\Collection $collection
     * @return array
     */
    public static function castCollection($collection)
    {
        return [
            Caster::PREFIX_VIRTUAL.'all' => $collection->all(),
        ];
    }
}
