<?php

namespace atk4\report\tests;

use atk4\data\Model;

class Payment extends Model
{
    public $table = 'payment';

    public function init()
    {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', Client::class);
        $this->addField('amount', ['type'=>'money']);
    }
}
