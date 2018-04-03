<?php
namespace atk4\report\tests\Model;

class Client extends \atk4\data\Model
{
    public $table = 'client';

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Payment', new Payment());
        $this->hasMany('Invoice', new Invoice());
    }
}
