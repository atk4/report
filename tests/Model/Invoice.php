<?php
namespace atk4\report\tests\Model;

class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->hasOne('client_id', new Client());
        $this->addField('amount', ['type' => 'money']);
    }
}
