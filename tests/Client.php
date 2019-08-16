<?php

namespace atk4\report\tests;

use atk4\data\Model;

class Client extends Model
{
    public $table = 'client';

    function init() {
        parent::init();
        $this->addField('name');

        $this->hasMany('Payment', new Payment());
        $this->hasMany('Invoice', new Invoice());
    }
}
