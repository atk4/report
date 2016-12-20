# Agile Data Report Extension

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
