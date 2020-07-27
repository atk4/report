<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\report\GroupModel;

class GroupTest extends \atk4\schema\PhpunitTestCase
{
    /** @var array */
    private $init_db =
        [
            'client' => [
                ['name' => 'Vinny'],
                ['name' => 'Zoe'],
            ],
            'invoice' => [
                ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
                ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
                ['client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ],
            'payment' => [
                ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
                ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
            ],
        ];

    /** @var GroupModel */
    protected $g;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDB($this->init_db);

        $m_invoice = new Invoice($this->db);
        $m_invoice->getRef('client_id')->addTitle();

        $this->g = new GroupModel($m_invoice);
        $this->g->addField('client');
    }

    public function testGroupSelect()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], ['c' => ['expr' => 'count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'c' => 2],
                ['client' => 'Zoe', 'client_id' => '2', 'c' => 1],
            ],
            $g->export()
        );
    }

    public function testGroupSelect2()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => '2', 'amount' => 4.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelect3()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'min' => ['expr' => 'min([amount])', 'type' => 'money'],
            'max' => ['expr' => 'max([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'], // same as `s`, but reuse name `amount`
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'min' => 4.0, 'max' => 15.0, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'min' => 4.0, 'max' => 4.0, 'amount' => 4.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelectExpr()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $g->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelectCondition()
    {
        $g = $this->g;
        $g->master_model->addCondition('name', 'chair purchase');

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $g->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelectCondition2()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $g->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $g->addCondition('double', '>', 10);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelectCondition3()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $g->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $g->addCondition('double', 38);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $g->export()
        );
    }

    public function testGroupSelectCondition4()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $g->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $g->addCondition('client_id', 2);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $g->export()
        );
    }

    public function testGroupLimit()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $g->setLimit(1);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'amount' => 19.0],
            ],
            $g->export()
        );
    }

    public function testGroupLimit2()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $g->setLimit(1, 1);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => '2', 'amount' => 4.0],
            ],
            $g->export()
        );
    }
}
