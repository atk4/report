# Agile Data - Reportig Add-on

This extension for Agile Data implements advanced reporting capabilities:

-   Aggregate models. Provite grouping of existing model.
-   Union models. Combine one or multiple models.

## Installation and Usage

This repository is now public and available under MIT license, so to install:

``` shell
composer require atk4/report
```

You may need to adjust your `minimum-stability` setting. 

### UnionModel

Create a new model that combines data from scopes of several nested models. Will help you map fields properly so that resulting model have the columns you want. In next example you would need to define Purchase and Sale models yourself. See https://github.com/atk4/data):

``` php
$m = new \atk4\report\UnionModel($db);
$m->addNestedModel(new Purchase(), [
    'ref_no'        => '[ref_no]',          // mapping fields is optional
    'date'          => '[purchase_date]',   // you can alias like this
    'contact'       => 'concat("From: ", [contractor_from])',
]);                                         // use expressions
$m->addNestedModel(new Sale(), [
    'date'          => '[sale_date]',       // this way we have one column for dates
    'contact'       => 'concat("To: ", [contractor_to])',
]);
$m->setOrder(['date']);                     // sorts resulting union model

$m->addField('date', ['caption' => 'Date', 'type' => 'date']);
     // now add union-based models here.

$m->addField('contact', ['caption' => 'Supplier/Payee']);
$m->addField('ref_no', ['caption' => 'Document No']);
$m->addField('amount', ['caption' => 'Net Amount', 'type' => 'money']);
     // if association in nested model is not explicitly defined, will
     // use field. If no field is found, will use expression: "null"

$m->join('country', 'country_id')
    ->addField('country', 'name');
     // Union Model extends \atk4\data\Model so you can use addField and addExpression


// $m->groupBy('country', ['amount'=>'sum([]')])
// groupBy works like in GroupModel, next example for usage.

$table->setModel($m);
```

### GroupModel

Creates a new model containing aggregate data from your existing model. Note that you can combine UnionModel and GroupModel recursively.

```php
$m = new \smbo\GroupModel(new Sale($db));
$m->groupBy(['contractor_to', 'type'], [      // groups by 2 columns
    'c'                     => 'count(*)',    // defines aggregate formulas for fields
    'qty'                   => 'sum([])',     // [] refers back to qty
    'total'                 => 'sum([amount])', // can specify any field here
]);

$m->addFields(['vat_registered', ['vat_no', 'caption' => 'VAT No']]);
    // add 2 more fields which will bypass aggregation.

$m->getElement('total')->type = 'money';
    // change the type

```



## Documentation

https://github.com/atk4/report/blob/develop/docs/index.md

## Current Status

Implementation is complete, but a better documentation and more examples needed. Also some cleanups in the code are welcome!

## License: MIT