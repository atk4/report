<?php
namespace atk4\report;

/**
 * UnionModel combines multiple nested models through a UNION in order to retrieve
 * it's value set. The beauty of this class is that it will add fields transparently
 * and will map them appropriatelly from the nested model if you request
 * those fields from the union model.
 *
 * For example if you are asking sum(amount), there is no need to fetch any extra
 * fields from sub-models.
 */
class UnionModel extends \atk4\data\Model {

    /**
     * Contain array of array containing model and mappings:
     *
     * $union = [ [ $m1, ['amount'=>'total_gross'] ] , [$m2, []] ];
     */
    public $union = [];

    /**
     * Union normally does not have ID field. Setting this to null will
     * disable various per-id operations, such as load();
     *
     * If you can define unique ID field, you can specify it inside your
     * union model.
     */
    public $id_field = null;

    /**
     * When aggregation happens, this field will contain list of fields
     * we use in groupBy. Multiple fields can be in the array. All
     * the remaining fields will be hidded (marked as system()) and
     * have their "aggregates" added into the selectQuery (if possible)
     */
    public $group = null;

    /**
     * When grouping, the functions will be applied as per aggregate
     * fields, e.g. 'balance'=>['sum', 'amount'];
     *
     * You can also use Expression instead of array.
     */
    public $aggregate = [];

    public $table = 'derivedTable';

    /**
     * Will be initialized during the first query and then subsequently used
     * as a FROM value when executing action()'s
     */
    public $table_expr = null;



    /**
     * Configures nested models no have a specified set of fields
     * available
     */
    function setNestedModelFields($fields) {

        foreach($m->union as $model_def) {
            list($model, $mapping) = $model_def;

            // map fields for related model
            $f = [];
            foreach($fields as $field){ 

                // Union can be joined with additional
                // table/query and we don't touch those 
                // fields
                if ($this->getElement($field)->join) {
                    continue;
                }

                // Union can have some fields defined
                // as expressions. We don't toch those
                // either
                if ($this->getElement($field) instanceof \atk4\data\Field_SQL_Expression) {
                    continue;
                }

                // Some fields are re-mapped for this nested model
                if(isset($mapping[$field])) {

                    // When grouping, we could be aggregating this field
                    if (isset($this->aggregate[$field])) {
                        $model->addExpression(
                            $field, 
                            $this->aggregate[$field].'('.$mapping[$field].')'
                        );
                    } else {
                        $model->addExpression($field, $mapping[$field]);
                    }
                } else {
                    if(!$model->hasElement($field)) {
                        $model->addExpression($field, 'null');
                    }
                }

                $f[] = $field;
            }

            // now prepare query
            $expr[] = '['.$cnt.']';
            $q = $this->persistence->action($model, 'select', [$f]);
            if($this->group) {
                $q->group($this->group);
            }
            $args[$cnt++] = $q;
        }


    }










    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'insert':
            case 'update':
            case 'delete':
                throw new Exception(['UnionModel does not support this action', 'action'=>$mode]);
        }

        $only_fields_pref = $this->only_fields;

        if(!$this->only_fields) {
            $fields = []; 

            // get list of available fields
            foreach($this->elements as $key=>$f) {
                if ($f instanceof \atk4\data\Field) {
                    $fields[] = $key;
                }
            }
        } else {
            $fields = $this->only_fields;
        }

        $this->setNestedModelFields($fields);

        switch ($mode) {
            case 'select':
                $this->initQueryFields($m, $q, isset($args[0]) ? $args[0] : null);
                break;

            case 'count':
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q]);
                $q->reset('field')->field('count(*)');

                return $q;

            case 'field':
                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }

                $field = is_string($args[0]) ? $m->getElement($args[0]) : $args[0];
                $m->hook('initSelectQuery', [$q, $type]);
                $q->reset('field')->field($field);
                $this->initQueryConditions($m, $q);
                $this->setLimitOrder($m, $q);

                return $q;

            case 'fx':
                if (!isset($args[0], $args[1])) {
                    throw new Exception([
                        'fx action needs 2 arguments, eg: ["sum", "amount"]',
                        'action' => $type,
                    ]);
                }

                $fx = $args[0];
                $field = is_string($args[1]) ? $m->getElement($args[1]) : $args[1];
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q, $type]);
                $q->reset('field')->field($q->expr("$fx([])", [$field]));

                return $q;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type' => $type,
                ]);
        }
    }



    protected $union_initialized = false;
    function getIterator() {
        $m = $this;

        if($this->union_initialized) {
            throw new Exception('Please do not use UNION model multiple times.');
        }
        $this->union_initialized = true;


        $cnt = 0;
        $expr= [];
        $args= [];

        $args[$cnt] = 'derivedTable';
        $q = $this->persistence->dsql()->expr('('.join(' UNION ALL ',$expr).') {'.$cnt.'}', $args);
        $this->addHook('initSelectQuery', function($m,$e)use($q) {
            $e->reset('table')->table($q);
        });

        return parent::getIterator();
    }

    function export($fields = null)
    {
        if($fields) {
            $this->onlyFields($fields);
        }

        $data = [];
        foreach($this->getIterator() as $row) {
            $data[] = $row->get();
        }

        return $data;
    }


    function addNestedModel($class, $mapping = []){

        $m = $this->persistence->add($class);
        $this->union[] = [$m, $mapping];
        return $m;
    }

    /**
     * Specify a single field or array of fields
     */
    function groupBy($group, $aggregate = [])
    {

        $this->group = $group;
        $this->aggregate = $aggregate;
        return $this;
    }

    function addCondition($field, $operator = null, $value = null)
    {
        if(func_num_args() == 1) {
            return parent::addCondition($field);
        }

        foreach($this->union as $n=>list($model, $mapping)){
            try {
                $ff = $field;
                if (isset($mapping[$field])) {
                    $ff = $mapping[$field];
                }
                if($ff[0] == '[') {
                    $ff = substr($ff,1,-1);
                }
                switch(func_num_args()) {
                case 2:
                    $model->addCondition($ff, $operator);
                    break;
                case 3:
                    $model->addCondition($ff, $operator, $value);
                    break;
                }
            }catch(\atk4\core\Exception $e) {
                $e->addMoreInfo('sub_model', $n);
                throw $e;
            }
        }
        return $this;
    }
}
