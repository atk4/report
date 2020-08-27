<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\data\Model;

class Client extends Model
{
    public $table = 'client';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasMany('Payment', [Payment::class]);
        $this->hasMany('Invoice', [Invoice::class]);
    }
}
