<?php

namespace atk4\report\tests;

class Client extends \atk4\data\Model 
{
    public $table = 'client';

    function init() {
        parent::init();
        $this->addField('name');

        $this->hasMany('Payment', new Payment());
        $this->hasMany('Invoice', new Invoice());
    }
}
