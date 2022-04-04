<?php

namespace Winter\Search\Behaviors;

use Winter\Storm\Extension\ExtensionBase;

class Searchable extends ExtensionBase
{
    /**
     * Constructor for the behaviour.
     *
     * Attaches listeners to the model.
     *
     * @param \Winter\Storm\Database\Model|\Winter\Storm\Halcyon\Model $model
     */
    public function __construct($model)
    {

    }
}
