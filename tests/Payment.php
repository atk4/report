<?php

namespace atk4\report\tests;
use atk4\data\Model;

class Payment extends Model
{
    public $table = 'payment';

    function init() {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', 'Client');
        $this->addField('amount', ['type'=>'money']);
    }
}
