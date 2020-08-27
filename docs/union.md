# Agile Data UnionModel

In many cases report would have to contain data from multiple models. I'll use the following example:

### Example I'll be using

In my model scheam, Client may have multiple invoices and multiple payments. Payment is not related to the invoice.

``` php
class Client extends \atk4\data\Model {
  public $table = 'client';
  
  protected function init() {
  	parent::init();
      $this->addField('name');
    
      $this->hasMany('Payment');
      $this->hasMany('Invoice');
  }
}
```

(see tests/Client.php, tests/Payment.php and tests/Invoice.php files).

## Define a Union Model

Normally model is associated with a single table. UnionModel can have multiple nested models defined and it draws results from that. As a result, UnionModel will have no "id" field. Inside the init() body of UnionModel define:

``` php
$m_uni = new \atk4\report\UnionModel();

$m_pay = $m_uni->addNestedModel(new Invoice());
$m_inv = $m_uni->addNestedModel(new Payment());
```

Next, assuming that both models have a commont fields "name" and "amount", use this:

``` php
$m_uni->addField('name');
$m_uni->addFiled('amount', ['type'=>'money']);
```

Finally you can query data:

``` php
var_dump($m_uni->export());
```

## Types of Union Model fields

Let's look at 3 different ways to define fields for Union Model

``` php
// Will link the "name" field will all the nested models.
$m_uni->addField('client_id'); 

// Expression will not affect nested models in any way
$m_uni->addExpression('name_capital','upper([name])');

// Union model can be joined with extra tables and define
// some fields from those joins
$m_uni->join('client','client_id')
  ->addField('client_name', 'name');
```

Expressions and Joins are working just as they would on any other model, so refer to main documentation of Agile Data:

-   http://agile-data.readthedocs.io/en/develop/expressions.html
-   http://agile-data.readthedocs.io/en/develop/joins.html

## Field Mapping

Sometimes, the field you define may be named differently inside nested model. For instance, Invoice has field "description". Payment has field "note". When defining a nested model, you can specify field mapping:

``` php
$m_pay = $m_uni->addNestedModel(new Invoice());
$m_inv = $m_uni->addNestedModel(new Payment(), ['description'=>'[note]']);
$m_uni -> addField('description');
```

The left side of the mapping array must match the Union field. The right side is an expression. (See Model::addExpression()). This format can also be used to reverse sign on amounts. When we are creating "Transactions", then invoices would have a negative impact on the amount, while payments have a positive impact:

``` php
$m_pay = $m_uni->addNestedModel(new Invoice(), ['amount'=>'-[amount]']);
$m_inv = $m_uni->addNestedModel(new Payment(), ['description'=>'[note]']);
```

Should you need more flexibility, remember that you can add more expressions (or fields) directly inside nested models at this stage:

``` php
$m_pay = $m_uni->addNestedModel(new Invoice(), ['amount'=>'-[amount]']);
$m_inv = $m_uni->addNestedModel(new Payment(), ['description'=>'[note]']);

$m_pay->addExpression('type', '"payment"');
$m_inv->addExpression('type', '"invoice"');
$m_uni->addField('type');
```

I have defined a new column "type" that will be defined as a static constant. Again, this may differ from between nested models.

## Referencing a Union Model

Like any other model, you can assign Union model through a reference. In our case one Client can have multiple transactions. For a start, let's define a related union:

``` php
$client->hasMany('Transaction', new Transaction());
```

When condition is added on a UnionModel it will send it down to the every nested model. This way your SQL query remains most efficient.

The exception is of course when field is not mapped to nested model (if it's an expression or associated with a join).

In most cases you do not have to worry about optimizing your query and ModelUnion will simply work.

## Grouping Results

ModelUnion has a built-in grouping support.

``` php
$m_uni->groupBy('client_id', ['amount'=>'sum']);
```

When specifying a field to be used for grouping, if the field is associated with nested models, then grouping will be enabled on every nested model.











Report extension contains collection of various tools that can make data aggrigation in Agile Data simpler. We will continue to add more report-related functionality for this extension, so if you can think of any new ideas on what you would like to see here, please share with our developent team.

## Aggregate Extension

Aggregate adds few new actions which you can use:

### Grouping

``` php
$orders->add(new \atk4\report\Aggregate());

$aggregate = $orders->action('group');
```

$aggregate above will return a new object that's most appropriate for your persistence and which can be manipulated in various ways to fine-tune aggregation. Below is one sample use:

``` php
$aggregate = $orders->action(
  'group',
  'country_id', 
  [
    'country',
    'count'=>'count',
    'total_amount'=>['sum', 'amount']
  ],
);

foreach($aggregate as $row) {
  var_dump(json_encode($row));
  // ['country'=>'UK', 'count'=>20, 'total_amount'=>123.20];
  // ..
}
```







Here is how we can build opening balance:



``` php
$ledger = new Report_Ledger($db);
$ledger->addCondition('date', '<', $from);

// we actually need grouping by nominal
$ledger->add(new \atk4\report\Aggregate());
$by_nominal = $ledger->action('group', 'nominal_id');
$by_nominal->addField('opening_balance', ['sum', 'amount']);
$by_nominal->join()

```







## Union Model

Regular model are capable of reading and updating records.



## Aggregate Extension









Audit Extension provides a mechanism to store all changes that happen during persistance of your model. This extension is designed to be extensive and flexible. Use Audit if you need to track changes performed by your users in great detail.

Audit supports a wide varietty of additional features such as ability to **undo** actions, record actions that have **failed** to execute (due to validation) along with the error, **retry** failed actions, retrieve **historical** records without modifying database, log **custom** actions and even **replay** all actions. Audit also records which **field values** were changed inside a model before executing `save()` and which fields were changed **reactively** (through other hooks) and will track and link reactive modifications to **multiple models**.

Huge focus on extensibility allow you to **customise** name of log table, change field names, database **engine** (e.g. store in CSV file, API or Cloud Database), **switch off** certain features, customise **human-readable** log entries and add additional information about **user**, **session** or **environment**.

(See also - [Full Example](full-example.md))

## Enabling Audit Log

To enable extension for your model, add the following line into Model's method `init`:

``` php
$this->add(new \atk4\audit\Controller());
```

For a basic usage you will also need to create `audit_log` table by importing `audit_log.sql` file. The audit-log is automatically populated when you perform an operation with the model next time:

``` php
$m->load(1);
$m['name'] = 'Ken'; // was Vinny before
$m->save();
```

The following new record will be stored inside `audit_log` table:

``` json
{  
   "id":1,
   "initiator_audit_log_id":null,
   "ts":{  
      "date":"2016-10-03 21:44:14.000000",
      "timezone_type":3,
      "timezone":"UTC"
   },
   "model":"atk4\\ui\\tests\\AuditableUser",
   "model_id":"1",
   "action":"update",
   "time_taken":0.00174,
   "descr":"update name=Ken",
   "user_info":null,
   "request_diff":{  
      "name":[  
         "Vinny",
         "Ken"
      ]
   },
   "reactive_diff":null,
   "is_reverted":null,
   "revert_audit_log_id":null
}
```

Here are some more advanced topics:

-   [Enable AuditLog for all your Models](system-wide.md)
-   [Configure which fields are logged](field-config.md)
-   [Custom event logging and customizing](custom.md)
-   [Various storage options](storage.md)

## Working With the Log Entries

Your model contains reference to AuditLog model. Let's see how many times the above record have been modified in the past:

``` php
echo $m->load(1)->ref('AuditLog')->action('count')->getOne();  // 1
```

You can also use it to access records individually or just access last record:

``` php
$m->load(1)->ref('AuditLog')->loadLast()->undo(); // revert last action
```

If you wish to undo all the actions for specific record, run:

```php
$yesterday = new DateTime();
$yesterday->sub(new DateInterval('P1D'));

$m->load(1)->ref('AuditLog')
    ->addCondition('date', '>=', $yesterday)
    ->addCondition('is_reverted')
    ->each('undo');
    // revert all actions, that have happened today
    // but exclude those that have been reverted already
```



More in depth:

-   [How does undo() and redo() work](undo.md)
-   [Recording sequences for your unit-tests](unit-tests.md)
-   [Fetching historical records](historical.md)

## Requested and Reactive field changes

AuditLog extension records fields that were `dirty` before execution of save() operation. Sometimes you would have a logic inside your model hooks that can change more records or even change other models. For example if you change `InvoiceLine` amount it might want to update amount of `Inovice` too.



## Requested vs Reactive actions

Agile Data incorporates rich volume of logic that allow you to make a lot of decision across the system when even a smallest change is requested. For example assuming you have the following structure:

-   Invoice
    -   `addFields(['total_net', 'total_vat', 'total_gross'], ['type' => 'money']);`
    -   `hasMany('Line')`
        -   `addField('qty', ['type => 'int'])`
        -   `addField('vat_rate', ['type' => 'float'])`
        -   `addFields(['price', 'vat', 'net', 'gross'], ['type' => 'money']);`

Your `afterSave` hooks will automatically recalculate and update `Invoice` whenever you change the `Line`. Additionally, changing `qty` will trigger change in `vat`, `net` and `gross`.

Looking at he following code:

``` php
$m = new Invoice($db);
$m->load(1);
$m['qty']++;
$m->save();
```

Only a single field falls into "requested" change, which is `qty`. The original value and a new value will be stored in JSON:

``` json
{"qty": [5, 6]}
```

However due to hooks, many other values have also been updated. For the Line the "reactive" changes are:

``` json
{"net": [50, 60], "vat": [11.5, 13.8], "gross": [51.5, 63.8]}
```

Then you have some "reactive" changes for the `Invoice` model too:

``` php
{"total_net": [100, 110], "total_vat": [23.0, 25.3], "total_gross": [123.0, 135.3]}
```

The default way for Audit Log is to store only "reactive" changes, however you can enable storing of both "requested" and "reactivte":

``` php
$audit = new \atk4\audit\Controller([
    new Audit(),
    ['requested_log' => true, 'reactive_log' => true, 'link' => true]
);
```

The option for `nested` will also associate changes inside `Invoice` with the requested changes of `Line`. If you need to customise settings on per-model basis, you should create individual controllers.

## How values are stored?

Audit Extension uses array persistence to prepare values for storage inside JSON. If you need to tweak how values are stored exactly, you shourd refer to documentation on [typecasting](http://agile-data.readthedocs.io/en/develop/persistence.html?highlight=typecasting#type-converting).

System is storing using business-domain field names. If "net" has an actual field of "sql_net", then audit will store "net". Additionally 

## Undo and Replay features

Agile Audit Extension perform a strict type auditing which can be quite useful for automation. Certain actions can be "undone" or "replayed" (Redo).

Those action can be performed on the `Audit` model after you load the record:

``` php
$audit->load(20);
$audit->replay();
```

Assuming that audit log with ID=20 corresponds to the `qty` modifications as I was explaining above, the replay will perform the following:

-   start transaction
-   load model for `Line`
-   perform modification of `qty`
-   save value of `qty`
-   track changed fields in `Line` and `Invoice`
-   assert to make sure all the same reactive changes happened
-   commit

Similarly you can also call `undo()`, which will:

-   Reverse all `new` / `old` values for original Audit event and all related ones
-   Attempts to apply the replay()

Both `Undo` and `Replay` functionality can bypass the verification steps or can actually enforce `Reactive` changes to be used. Those modes are less safe but if that's what you want you can try it.

Finally, Replay feature can also override `id` of the original model. In this scenario changes will be re-applied to a different record. 

### When Undo and Replay are useful?

Undo can be offered to a user as an option. Because implementation of `Undo` especially in transaction-supporting database is pretty safe, you can execute multiple `Undo` actions effectively allowing you to walk between revisions of your persistence.

``` php
$audit = new Audit($db);
$audit->addCondition('user_id', $this->app->user->id);
$audit->addCondition('date', '>', $unroll_to_date);
$audit->setOrder('id desc');
$audit->each('undo');
```

Applying `Replay` on the range of entries makes a pretty effective multi-record update technique.

```php
$invoices = $client->ref('Invoice'); // references multiple invoices
$invoices->add($audit);

// record action
$invoices->loadAny();
$invoices->save( $new_data );

$audit->last_action->applyOnOthers($invoices);
```

Finally, replay can be used in creating unit tests. If you have enabled Audit Log for your application and have already performed some actions, you can generate  `PHPUnit`-compatible code through Admin Audit Page.

## Admin Page

Audit Extension comes with [Agile UI](https://github.com/atk4/ui) based page that contains a handy management console where you can browse all the recent events, convert them into unit-tests, undo or re-apply some of those. Additionally selecting an event will also show you all the "Reactive" actions that have been done.

![data-audit-1-console](images/data-audit-1-console.png)

## Download and Install

Audit Extension is currently in Beta. You need to contact us if you wish to get early access.
