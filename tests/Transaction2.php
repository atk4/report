<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\report\UnionModel;

class Transaction2 extends UnionModel
{
    public function init(): void
    {
        parent::init();

        // first lets define nested models
        $this->m_invoice = $this->addNestedModel(new Invoice(), ['amount' => '-[]']);
        $this->m_payment = $this->addNestedModel(new Payment());

        //$this->m_invoice->hasOne('client_id', new Client());
        //$this->m_payment->hasOne('client_id', new Client());

        // next, define common fields
        $this->addField('name');
        $this->addField('amount', ['type' => 'money']);
        //$this->hasOne('client_id', new Client());
    }
}
