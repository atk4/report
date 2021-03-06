<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\report\GroupModel;

class Report1Test extends \atk4\schema\PhpunitTestCase
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

        $m1 = new Invoice($this->db);
        $m1->getRef('client_id')->addTitle();
        $this->g = new GroupModel($m1);
        $this->g->addField('client');
    }

    public function testAliasGroupSelect()
    {
        $g = $this->g;

        $g->groupBy(['client_id'], ['c' => ['count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'c' => 2],
                ['client' => 'Zoe', 'client_id' => '2', 'c' => 1],
            ],
            $g->export()
        );
    }
}
