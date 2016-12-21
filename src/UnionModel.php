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
     * UnionModel should always be read-only.
     */
    public $read_only = true;

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
    //public $table_expr = null;


    /**
     * Configures nested models no have a specified set of fields
     * available
     */
    function getSubQuery($fields) {

        $cnt = 0;
        $expr= [];
        $args= [];


        foreach($this->union as $model_def) {
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
                if ($this->getElement($field) instanceof \atk4\data\Field_SQL_Expression && !isset($this->aggregate[$field])) {
                    continue;
                }

                $field_object = null;

                // Some fields are re-mapped for this nested model
                if(isset($mapping[$field])) {
                    $e = $model->expr($mapping[$field]);

                    $model->getElement($field)->destroy();

                    $field_object = $model->addExpression($field, $e);

                } elseif (!$model->hasElement($field)) {
                    $field_object = $model->addExpression($field, 'NULL');
                } else {
                    $field_object = $model->getElement($field);
                }

                if (isset($this->aggregate[$field])) {

                    if(!isset($model->aggregates_applied)) {
                        $model->aggregates_applied = [];
                    }

                    if(!in_array($field, $model->aggregates_applied)) 
                    {
                        // generate query
                        $e = $model->expr($this->aggregate[$field]);

                        // replace original field with query
                        $field_object->destroy();

                        $model->aggregates_applied[] = $field;

                        $field_object = $model->addExpression($field, $e);
                    }
                }

                $f[] = $field;
            }

            // now prepare query
            $expr[] = '['.$cnt.']';
            $q = $this->persistence->action($model, 'select', [$f]);

            // also for sub-queries
            if($this->group) {
                $q->group($this->group);
            }
            $args[$cnt++] = $q;
        }
        $args[$cnt] = 'derivedTable';
        return $this->persistence->dsql()->expr('('.join(' UNION ALL ',$expr).') {'.$cnt.'}', $args);
    }

    function getSubAction($action, $act_arg=[]){
        $cnt = 0;
        $expr= [];
        $args= [];


        foreach($this->union as $model_def) {
            list($model, $mapping) = $model_def;

            // now prepare query
            $expr[] = '['.$cnt.']';
            $q = $model->action($action, $act_arg);

            $args[$cnt++] = $q;
        }
        $args[$cnt] = 'derivedTable';
        return $this->persistence->dsql()->expr('('.join(' UNION ALL ',$expr).') {'.$cnt.'}', $args);
    }

    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'insert':
            case 'update':
            case 'delete':
                throw new Exception(['UnionModel does not support this action', 'action'=>$mode]);
        }

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

        $subquery = null;

        switch ($mode) {
            case 'select':
                $subquery = $this->getSubQuery($fields);
                $query = parent::action($mode, $args);
                $query->reset('table')->table($subquery);

                if($this->group) {
                    $query->group($this->getElement($this->group));
                }
                return $query;


                break;

            case 'count':
                $subquery = $this->getSubAction('count', ['alias'=>'cnt']);

                $query = parent::action('fx', ['sum', new \atk4\dsql\Expression('`cnt`')]);
                $query->reset('table')->table($subquery);
                return $query;

            case 'field':
                if (!is_string($args[0])) {
                    throw new Exception(['action(field) only support string fields', 'field'=>$arg[0]]);
                }

                $subquery = $this->getSubQuery([$args[0]]);

                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }
                break;

            case 'fx':

                $subquery = $this->getSubAction('fx', [$args[0], $args[1], 'alias'=>'val']);

                $query = parent::action('fx', [$args[0], new \atk4\dsql\Expression('`val`')]);
                $query->reset('table')->table($subquery);
                return $query;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'mode' => $mode,
                ]);
        }

        $query = parent::action($mode, $args);

        // Next - substitute FROM table with our subquery expression
        $query->reset('table')->table($subquery);
        return $query;
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


        foreach($aggregate as $field=>$expr) {

            $e = $this->expr($expr);

            $field_object = $this->hasElement($field);
            if ($field_object) { 
                $field_object->destroy();
            }

            $field_object = $this->addExpression($field, $e);
        }

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
