<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\report\UnionModel;

class Transaction extends UnionModel
{
    public function init(): void
    {
        parent::init();

        // first lets define nested models
        $this->m_invoice = $this->addNestedModel(new Invoice());
        $this->m_payment = $this->addNestedModel(new Payment());

        // next, define common fields
        $this->addField('name');
        $this->addField('amount', ['type' => 'money']);
    }
}
