<?php

namespace atk4\report\tests;


/**
 * Tests basic create, update and delete operatiotns
 */
class Report1Test extends \atk4\schema\PHPUnit_SchemaTestCase
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

    function setUp() {
        parent::setUp();
        $m1 = new Invoice($this->db);
        $m1->getRef('client_id')->addTitle();
        $this->g = new \atk4\report\GroupModel($m1);
        $this->g->addField('client');
    }

    public function testAliasGroupSelect()
    {
        $g = $this->g;

        $g->groupBy(['clienit_id'], ['c'=>'count(*)']);

        $this->assertEquals(
            [
                ['client'=>'Vinny','client_id'=>1, 'c'=>2],
                ['client'=>'Zoe','client_id'=>2, 'c'=>1],
            ],
            $g->export()
        );
    }

}
