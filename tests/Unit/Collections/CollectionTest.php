<?php

namespace Tests\Unit\Collections;

use Tests\BaseTestCase;

class CollectionTest extends BaseTestCase
{
    /**
     * A simple array for testing.
     *
     * @var array
     */
    private $simpleArr = [
        'dharma',
        'hanso'
    ];

    /**
     * An associated array for testing.
     *
     * @var array
     */
    private $assocArr = [
        'swan' => 'locke',
        'arrow' => 'eko'
    ];

    /**
     * A nested array for testing.
     *
     * @var array
     */
    private $nestArr = [
        [
            'live' => 'together',
            'die' => 'alone'
        ],
        [
            'wa' => 'aalt',
            'not' => 'pennys boat'
        ]
    ];

    /**
     * Ensures that the collection's underlying array can be returned.
     *
     * @test
     * @return void
     */
    public function checkAll()
    {
        $col = collect($this->simpleArr);
        $this->assertEquals($this->simpleArr, $col->all());
    }

    /**
     * Ensures that all() returns empty array for empty collection.
     *
     * @test
     * @return void
     */
    public function checkAllEmptyCollection()
    {
        $col = collect();
        $arr = $col->all();

        $this->assertEmpty($arr);
        $this->assertIsArray($arr);
    }

    /**
     * Ensures that the count of a collection of values can be returned.
     *
     * @test
     * @return void
     */
    public function checkCount()
    {
        $col = collect($this->simpleArr);
        $this->assertEquals(2, $col->count());
    }

    /**
     * Ensures that the count of a collection with key/value pairs can be returned.
     *
     * @test
     * @return void
     */
    public function checkCountAssocArr()
    {
        $col = collect($this->assocArr);
        $this->assertEquals(2, $col->count());
    }

    /**
     * Ensures that the count of a collection of associative arrays can be returned.
     *
     * @test
     * @return void
     */
    public function checkCountNestArr()
    {
        $col = collect($this->nestArr);
        $this->assertEquals(2, $col->count());
    }

    /**
     * Ensures that the count of an empty collection returns 0.
     *
     * @test
     * @return void
     */
    public function checkCountEmpty()
    {
        $col = collect();
        $this->assertEquals(0, $col->count());
    }
}
