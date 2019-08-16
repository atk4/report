<?php

namespace atk4\report\tests;
use atk4\data\Model;

class Invoice extends Model
{
    public $table = 'invoice';

    function init() {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', new Client());
        $this->addField('amount', ['type'=>'money']);
    }
}
