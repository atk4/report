<?php
// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\report;

use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\data\Reference;
use atk4\dsql\Query;

/**
 * GroupModel allows you to query using "group by" clause on your existing model.
 * It's quite simple to set up:
 *
 * $gr = new GroupModel($mymodel);
 * $gr->groupBy(['first','last'], ['salary'=>'sum([])'];
 *
 * your resulting model will have 3 fields:
 *  first, last, salary
 *
 * but when querying it will use the original model to calculate the query, then add grouping and aggregates.
 *
 * If you wish you can add more fields, which will be passed through:
 *
 * $gr->addField('middle');
 *
 * If this field exist in the original model it will be added and you'll get exception otherwise. Finally you are
 * permitted to add expressions.
 *
 * The base model must not be UnionModel or another GroupModel, however it's possible to use GroupModel as nestedModel inside UnionModel.
 * UnionModel implements identical grouping rule on its own.
 */
class GroupModel extends Model
{
    /**
     * GroupModel should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /** @var Model */
    public $master_model = null;

    /** @var string */
    public $id_field = null;

    /** @var array */
    public $group = [];

    /** @var array */
    public $aggregate = [];

    /** @var array */
    public $system_fields = [];

    /**
     * Constructor.
     *
     * @param Model $model
     * @param array $defaults
     *
     * @return GroupModel
     */
    public function __construct(Model $model, $defaults = [])
    {
        $this->master_model = $model;
        $this->table = $model->table;

        //$this->_default_class_addExpression = $model->_default_class_addExpression;
        parent::__construct($model->persistence, $defaults);
    }

    /**
     * Specify a single field or array of fields on which we will group model.
     *
     * @param array $group Array of field names
     * @param array $aggregate Array of aggregate mapping
     *
     * @return $this
     */
    public function groupBy($group, $aggregate = [])
    {
        $this->group = $group;
        $this->aggregate = $aggregate;

        $this->system_fields = array_unique($this->system_fields + $group);
        foreach ($group as $field) {
            $this->addField($field);
        }

        foreach ($aggregate as $field=>$expr) {


			 //$this->addField($field);
            // field originally defined in the parent model
            $field_object = $this->master_model->hasField($field); // use hasField here!

            // can be used as part of expression
            $e = $this->master_model->expr($expr, [$field_object]);

            // now add the expressions here
            $field_object = $this->addExpression($field, $e);
        }

        return $this;
    }

    /**
     * Return reference field.
     *
     * @param string $link
     *
     * @return Field
     */
    public function getRef($link) : Reference
    {
        return $this->master_model->getRef($link);
    }

    /**
     * Adds new field into model.
     *
     * @param string       $name
     * @param array|object $defaults
     *
     * @return Field
     */
    public function addField($name, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = [$defaults];
        }
    
        if (
            isset($defaults[0]) && $defaults[0] instanceof Field_SQL_Expression
            || isset($defaults['never_persist']) && $defaults['never_persist']
        ) {
            return parent::addField($name, $defaults);
        }
        
        $field = $this->master_model->hasField($name);

        return parent::addField($name, $field ? array_merge([$field], $defaults) : $defaults);
    }

    /**
     * Given a query, will add safe fields in.
     *
     * @param Query $query
     * @param array $fields
     *
     * @return Query
     */
    public function queryFields(Query $query, $fields = [])
    {
        $this->persistence->initQueryFields($this, $query, $fields);

        return $query;
    }

    /**
     * Adds grouping in query.
     *
     * @param Query $query
     */
    public function addGrouping(Query $query)
    {
        foreach ($this->group as $field) {
            $el = $this->master_model->hasField($field);
            if ($el) {
                $query->group($el);
            } else {
                $query->group($this->expr($field));
            }
        }



    }

    /**
     * Sets limit.
     *
     * @param mixed ...$args
     *
     * @return $this
     *
     * @todo Incorrect implementation
     */
    public function setLimit($count, $offset = null)
    {
        $this->master_model->setLimit($count, $offset);

        return $this;
    }

    /**
     * Sets order.
     *
     * @param mixed ...$args
     *
     * @return $this
     *
     * @todo Incorrect implementation
     */


    /**
     * Set action.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'insert':
            case 'update':
            case 'delete':
                throw new Exception(['GroupModel does not support this action', 'action'=>$mode]);
        }

        // get list of available fields
        $available_fields = $this->only_fields ?: array_keys($this->getFields());

        $fields = $available_fields;
        /* Imants: I really don't have idea why we excluded expressions and never_persist fields before
        $fields = [];
        foreach ($available_fields as $field) {
            if ($this->getField($field)->never_persist) {
                continue;
            }
            if ($this->getField($field) instanceof Field_SQL_Expression) {
                continue;
            }
            $fields[] = $field;
        }
        */

        $subquery = null;
        switch ($mode) {
            case 'select':
                // select but no need your fields
                $query = $this->master_model->action($mode, [false]);
                $query = $this->queryFields($query, array_unique($fields + $this->system_fields));

                $this->addGrouping($query);
                $this->initQueryConditions($query);

                $this->hook('afterGroupSelect', [$query]);

                $query = $this->orderByQuery($query);

                return $query;

            case 'count':
                $query = $this->master_model->action($mode, $args);

                $query->reset('field')->field($this->expr('1'));
                $this->addGrouping($query);

                $this->hook('afterGroupSelect', [$query]);

                $q = $query->dsql();
                $q->table($this->expr("([]) der", [$query]));
                $q->field('count(*)');

                return $q;

            case 'field':
                if (!is_string($args[0])) {
                    throw new Exception(['action(field) only support string fields', 'field'=>$args[0]]);
                }

                $subquery = $this->getSubQuery([$args[0]]);

                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $mode,
                    ]);
                }
                break;

            case 'fx':

                $subquery = $this->getSubAction('fx', [$args[0], $args[1], 'alias'=>'val']);

                $query = parent::action('fx', [$args[0], $this->expr('val')]);
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

    /**
     * Applies Order By to the query
     *
     */
     function orderByQuery($query)
     {

       if ($this->order) {

         foreach ($this->order as $o) {

           if(is_string($o[1])){
             $query->order($o[0]." ".$o[1]);
           } else {
             $query->order($o[0],$o[1]);
           }
         }
       }

       return $query;

     }




    /**
     * Our own way applying conditions, where we use "having" for
     * fields
     *
     * @param Query $q
     *
     * @return Query
     */
    function initQueryConditions(Query $q)
    {
        $m = $this;
        if (!isset($m->conditions)) {
            // no conditions are set in the model
            return $q;
        }

        foreach ($m->conditions as $cond) {

            // Options here are:
            // count($cond) == 1, we will pass the only
            // parameter inside where()

            if (count($cond) == 1) {

                // OR conditions
                if (is_array($cond[0])) {
                    foreach ($cond[0] as &$row) {
                        if (is_string($row[0])) {
                            $row[0] = $m->getField($row[0]);
                        }
                    }
                }

                $q->where($cond[0]);
                continue;
            }

            if (is_string($cond[0])) {
                $cond[0] = $m->getField($cond[0]);
            }

            if (count($cond) == 2) {
                if ($cond[0] instanceof Field) {
                    $cond[1] = $this->persistence->typecastSaveField($cond[0], $cond[1]);
                    $q->having($cond[0]->actual ?: $cond[0]->short_name, $cond[1]);
                } else {
                    $q->where($cond[0], $cond[1]);
                }
            } else {
                if ($cond[0] instanceof Field) {
                    $cond[2] = $this->persistence->typecastSaveField($cond[0], $cond[2]);
                    $q->having($cond[0]->actual ?: $cond[0]->short_name, $cond[1], $cond[2]);
                } else {
                    $q->where($cond[0], $cond[1], $cond[2]);
                }
            }
        }

        return $q;
    }


    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return array_merge(parent::__debugInfo(), [
            'group' => $this->group,
            'aggregate' => $this->aggregate,
            'master_model' => $this->master_model->__debugInfo(),
        ]);
    }

    // }}}
}
