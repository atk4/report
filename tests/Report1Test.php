<?php

declare(strict_types=1);

namespace atk4\report\tests;

use atk4\report\GroupModel;
use atk4\schema\PHPUnit_SchemaTestCase;

/**
 * Tests basic create, update and delete operatiotns
 */
class Report1Test extends PHPUnit_SchemaTestCase
{
    private $init_db =
        [
            'client' => [
                ['name' => 'Vinny'],
                ['name' => 'Zoe'],
            ],
            'invoice' => [
                ['client_id'=>1, 'name'=>'chair purchase', 'amount'=>4],
                ['client_id'=>1, 'name'=>'table purchase', 'amount'=>15],
                ['client_id'=>2, 'name'=>'chair purchase', 'amount'=>4],
            ],
            'payment' => [
                ['client_id'=>1, 'name'=>'prepay', 'amount'=>10],
                ['client_id'=>2, 'name'=>'full pay', 'amount'=>4],
            ],
        ];

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

        $g->groupBy(['client_id'], ['c'=>'count(*)']);

        $this->assertEquals(
            [
                ['client'=>'Vinny','client_id'=>1, 'c'=>2],
                ['client'=>'Zoe','client_id'=>2, 'c'=>1],
            ],
            $g->export()
        );
    }
}
