<?php

namespace atk4\report\tests;

class Payment extends \atk4\data\Model 
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
