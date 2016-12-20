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

    public function testNestedQuery()
    {
        $t = new Transaction($this->db);

        $this->assertEquals(
            '((select `name` from `invoice`) UNION ALL (select `name` from `payment`)) `derivedTable`', 
            $t->getSubQuery(['name'])->render()
        );

        $this->assertEquals(
            '((select `name`,`amount` from `invoice`) UNION ALL (select `name`,`amount` from `payment`)) `derivedTable`', 
            $t->getSubQuery(['name', 'amount'])->render()
        );

        $this->assertEquals(
            '((select `name` from `invoice`) UNION ALL (select `name` from `payment`)) `derivedTable`', 
            $t->getSubQuery(['name'])->render()
        );

    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    function testMissingField() {
        $t = new Transaction($this->db);
        $t->m_invoice->addExpression('type','"invoice"');
        $t->addField('type');

        $this->assertEquals(
            '((select ("invoice") `type`,`amount` from `invoice`) UNION ALL (select (NULL) `type`,`amount` from `payment`)) `derivedTable`', 
            $t->getSubQuery(['type', 'amount'])->render()
        );
    }


    public function testActions()
    {
        $t = new Transaction($this->db);

        $this->assertEquals(
            'select `name`,`amount` from ((select `name`,`amount` from `invoice`) UNION ALL (select `name`,`amount` from `payment`)) `derivedTable`',
            $t->action('select')->render()
        );

        $this->assertEquals(
            'select `name` from ((select `name` from `invoice`) UNION ALL (select `name` from `payment`)) `derivedTable`',
            $t->action('field', ['name'])->render()
        );

        $this->assertEquals(
            'select sum(`cnt`) from ((select count(*) `cnt` from `invoice`) UNION ALL (select count(*) `cnt` from `payment`)) `derivedTable`',
            $t->action('count')->render()
        );

        $this->assertEquals(
            'select sum(`val`) from ((select sum(`amount`) `val` from `invoice`) UNION ALL (select sum(`amount`) `val` from `payment`)) `derivedTable`',
            $t->action('fx', ['sum', 'amount'])->render()
        );
    }

    public function testActions2()
    {
        $t = new Transaction($this->db);
        $this->assertEquals(5, $t->action('count')->getOne());

        $this->assertEquals(37, $t->action('fx', ['sum', 'amount'])->getOne());
    }


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

    function testGrouping1() {

        $t = new Transaction($this->db);

        $t->groupBy('name', ['amount'=>'sum([amount])']);

        $this->assertEquals(
            '((select `name`,sum(`amount`) `amount` from `invoice` group by `name`) UNION ALL (select `name`,sum(`amount`) `amount` from `payment` group by `name`)) `derivedTable`', 
            $t->getSubQuery(['name', 'amount'])->render()
        );
    }

    function testGrouping2() {

        $t = new Transaction($this->db);

        $t->groupBy('name', ['amount'=>'sum([amount])']);

        $this->assertEquals(
            'select `name`,sum(`amount`) `amount` from ((select `name`,sum(`amount`) `amount` from `invoice` group by `name`) UNION ALL (select `name`,sum(`amount`) `amount` from `payment` group by `name`)) `derivedTable`', 
            $t->action('select', [['name','amount']])->render()
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries
     */
    function testGrouping3()
    {
        $t = new Transaction($this->db);
        $t->groupBy('name', ['amount'=>'sum([amount])']);
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
}
