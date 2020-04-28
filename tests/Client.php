<?php

namespace atk4\report\tests;

use atk4\data\Model;

class Client extends Model
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
