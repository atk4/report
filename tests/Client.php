<?php

namespace atk4\report\tests;

class Client extends \atk4\data\Model 
{
    public $table = 'client';

    public function init()
    {
        parent::init();
        $this->addField('name');

        $this->hasMany('Payment', Payment::class);
        $this->hasMany('Invoice', Invoice::class);
    }
}
