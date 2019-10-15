<?php

namespace atk4\report\tests;

class Transaction extends \atk4\report\UnionModel 
{
    public function init()
    {
        parent::init();

        // first lets define nested models
        $this->m_invoice = $this->addNestedModel(new Invoice());
        $this->m_payment = $this->addNestedModel(new Payment());

        // next, define common fields
        $this->addField('name');
        $this->addField('amount', ['type'=>'money']);
    }
}
