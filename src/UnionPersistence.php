<?php
namespace smbo;



/**
 * Persistence will work with special ModelUnion model 
 * which describes how it unions other models. Persistence
 * is responsible of having vendor-specific functionality.
 */
class UnionPersistence extends \atk4\data\Persistence_SQL {
    public $persistence = null;

    function __construct($persistence) {
        $this->persistence = $persistence;
    }

    function prepareIterator(\atk4\data\Model $m){


    }

}
