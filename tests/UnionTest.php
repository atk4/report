<?php

namespace atk4\report\tests;


/**
 * Tests basic create, update and delete operatiotns
 */
class UnionTest extends \atk4\schema\PHPUnit_SchemaTestCase
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

    public function testBasics()
    {

        $this->setDB($this->init_db);

        $client = new Client($this->db);

        // There are total of 2 clients
        $this->assertEquals(2, $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $client->load(1);
        $this->assertEquals(19, $client->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $t = new Transaction($this->db);
        $this->assertEquals([
            ['name' =>"chair purchase", 'amount' => 4],
            ['name' =>"table purchase", 'amount' => 15],
            ['name' =>"chair purchase", 'amount' => 4],
            ['name' =>"prepay", 'amount' => 10],
            ['name' =>"full pay", 'amount' => 4],
        ], $t->export());


        // Transaction is Union Model
        $client->hasMany('Transaction', new Transaction());

        $this->assertEquals([
            ['name' =>"chair purchase", 'amount' => 4],
            ['name' =>"table purchase", 'amount' => 15],
            ['name' =>"prepay", 'amount' => 10],
        ], $client->ref('Transaction')->export());
    }


    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries
     */
    function testSubGrouping()
    {
        $t = new Transaction($this->db);
        $t->groupBy('name', ['amount'=>'sum']);
        $t->setOrder('name');

        echo $t->action('select')->getDebugQuery();

        $this->assertEquals([
            ['name' =>"chair purchase", 'amount' => 8],
            ['name' =>"full pay", 'amount' => 4],
            ['name' =>"prepay", 'amount' => 10],
            ['name' =>"table purchase", 'amount' => 15],
        ], $t->export());

    }

    /**
     * If a nested model has a field defined through expression, it should be still used in grouping. We should test this
     * with both expressions based off the fields and static expressions (such as "blah")
     */
    function testSubGroupingByExpressions()
    {
    }

    /**
     * Sometimes we group by value that can emmit single records from sub-model, however according to the rule, we should
     * still roll them up on the top-level.
     */
    function testTopGrouping()
    {
    }

    /**
     * Text actions. Basically making sure that the UnionModel can act as a drop-in replacement into a UI such as
     * Grid with pagination
     */
    function testActions()
    {
    }
}
