<?php

declare(strict_types=1);

namespace atk4\report\tests;

class UnionExprTest extends \atk4\schema\PhpunitTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDB($this->init_db);

        $this->t = new Transaction2($this->db);
    }

    public function testFieldExpr()
    {
        $e = $this->getEscapeChar();
        $this->assertSame(str_replace('"', $e, '"amount"'), $this->t->expr('[]', [$this->t->getFieldExpr($this->t->m_invoice, 'amount')])->render());
        $this->assertSame(str_replace('"', $e, '-"amount"'), $this->t->expr('[]', [$this->t->getFieldExpr($this->t->m_invoice, 'amount', '-[]')])->render());
        $this->assertSame(str_replace('"', $e, '-NULL'), $this->t->expr('[]', [$this->t->getFieldExpr($this->t->m_invoice, 'blah', '-[]')])->render());
    }

    public function testNestedQuery1()
    {
        $t = $this->t;

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $t->getSubQuery(['name'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, '(select "name" "name",-"amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"'),
            $t->getSubQuery(['name', 'amount'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $t->getSubQuery(['name'])->render()
        );
    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    public function testMissingField()
    {
        $t = $this->t;
        $t->m_invoice->addExpression('type', '\'invoice\'');
        $t->addField('type');

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('`', $e, '(select (\'invoice\') `type`,-`amount` `amount` from `invoice` UNION ALL select NULL `type`,`amount` `amount` from `payment`) `derivedTable`'),
            $t->getSubQuery(['type', 'amount'])->render()
        );
    }

    public function testActions()
    {
        $t = $this->t;

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select "name","amount" from (select "name" "name",-"amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"'),
            $t->action('select')->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select "name" from (select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $t->action('field', ['name'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select sum("cnt") from (select count(*) "cnt" from "invoice" UNION ALL select count(*) "cnt" from "payment") "derivedTable"'),
            $t->action('count')->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select sum("val") from (select sum(-"amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"'),
            $t->action('fx', ['sum', 'amount'])->render()
        );
    }

    public function testActions2()
    {
        $t = $this->t;
        $this->assertSame(5, (int) $t->action('count')->getOne());
        $this->assertSame(-9.0, (float) $t->action('fx', ['sum', 'amount'])->getOne());
    }

    public function testSubAction1()
    {
        $t = $this->t;
        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select sum(-"amount") from "invoice" UNION ALL select sum("amount") from "payment") "derivedTable"'),
            $t->getSubAction('fx', ['sum', 'amount'])->render()
        );
    }

    public function testBasics()
    {
        $this->setDB($this->init_db);

        $client = new Client($this->db);

        // There are total of 2 clients
        $this->assertSame(2, (int) $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $client->load(1);
        $this->assertSame(19.0, (float) $client->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $t = new Transaction2($this->db);
        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $t->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', new Transaction2());

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'prepay', 'amount' => 10.0],
        ], $client->ref('Transaction')->export());
    }

    public function testGrouping1()
    {
        $t = $this->t;

        $t->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable"'),
            $t->getSubQuery(['name', 'amount'])->render()
        );
    }

    public function testGrouping2()
    {
        $t = $this->t;

        $t->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select "name",sum("amount") "amount" from (select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"'),
            $t->action('select', [['name', 'amount']])->render()
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries.
     */
    public function testGrouping3()
    {
        $t = $this->t;
        $t->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);
        $t->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => -15.0],
        ], $t->export());
    }

    /**
     * If a nested model has a field defined through expression, it should be still used in grouping. We should test this
     * with both expressions based off the fields and static expressions (such as "blah").
     */
    public function testSubGroupingByExpressions()
    {
        $t = $this->t;
        $t->m_invoice->addExpression('type', '\'invoice\'');
        $t->m_payment->addExpression('type', '\'payment\'');
        $t->addField('type');

        $t->groupBy('type', ['amount' => ['sum([])', 'type' => 'money']]);

        $this->assertSame([
            ['type' => 'invoice', 'amount' => -23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $t->export(['type', 'amount']));
    }

    public function testReference()
    {
        $c = new Client($this->db);
        $c->hasMany('tr', new Transaction2());

        $this->assertSame(19.0, (float) $c->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $c->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $c->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a ' .
            'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"'),
            $c->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()
        );
    }

    /**
     * Aggregation is supposed to work in theory, but MySQL uses "semi-joins" for this type of query which does not support UNION,
     * and therefore it complains about `client`.`id` field.
     *
     * See also: http://stackoverflow.com/questions/8326815/mysql-field-from-union-subselect#comment10267696_8326815
     */
    public function testFieldAggregate()
    {
        $c = new Client($this->db);
        $c->hasMany('tr', new Transaction2())
            ->addField('balance', ['field' => 'amount', 'aggregate' => 'sum']);

        $this->assertTrue(true); // fake assert
        //select "client"."id","client"."name",(select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" UNION ALL select sum("amount") "val" from "payment" where "client_id" = "client"."id") "derivedTable") "balance" from "client" where "client"."id" = 1 limit 0, 1
        //$c->load(1);
    }

    /**
     * Model's conditions can still be placed on the original field values.
     */
    public function testConditionOnMappedField()
    {
        $t = new Transaction2($this->db);
        $t->m_invoice->addCondition('amount', 4);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $t->export());
    }
}
