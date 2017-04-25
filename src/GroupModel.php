<?php
namespace atk4\report;

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
class GroupModel extends \atk4\data\Model
{
    public $read_only = true;

    public $master_model = null;

    public $id_field = null;

    public $group = null;

    public $aggregate = [];

    public $system_fields = [];

    public function __construct(\atk4\data\Model $model, $opts = [])
    {
        $this->master_model = $model;
        $this->table = $model->table;

        //$this->_default_class_addExpression = $model->_default_class_addExpression;
        return parent::__construct($model->persistence);
    }

    /**
     * Specify a single field or array of fields
     */
    public function groupBy($group, $aggregate = [])
    {
        $this->aggregate = $aggregate;
        $this->group = $group;

        $this->system_fields = array_merge($this->system_fields, $group);
        foreach($group as $field) {
            $this->addField($field);
        }

        foreach ($aggregate as $field=>$expr) {

            // field originally defined in the parent model
            $field_object = $this->master_model->hasElement($field);

            // can be used as part of expression
            $e = $this->master_model->expr($expr, [$field_object]);

            // now add the expressions here
            $field_object = $this->addExpression($field, $e);
        }

        return $this;
    }

    public function getRef($x)
    {
        return $this->master_model->getRef($x);
    }

    public function addField($f, $defaults = [])
    {
        if (isset($defaults['never_persist']) && $defaults['never_persist']) {
            return parent::addField($f, $defaults);
        }
        $defaults[0] = $f;
        return $this->add($this->master_model->getElement($f), $defaults);
    }

    /**
     * Given a query, will add safe fields in
     */
    public function queryFields($query, $fields = [])
    {
        $this->persistence->initQueryFields($this, $query, $fields);
        /*
        foreach($this->elements as $el) {
        }
         */

        return $query;
    }

    public function addGrouping($query)
    {
        foreach ($this->group as $field) {
            $el = $this->master_model->hasElement($field);
            if ($el) {
                $query->group($el);
            } else {
                $query->group($this->expr($field));
            }
        }
    }

    public function setLimit($count, $offset = null)
    {
        $this->master_model->setLimit($count, $offset);

        return $this;
    }

    public function setOrder($field, $desc = null)
    {
        $this->master_model->setOrder($field, $desc);

        return $this;
    }

    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'insert':
            case 'update':
            case 'delete':
                throw new Exception(['GroupModel does not support this action', 'action'=>$mode]);
        }

        if (!$this->only_fields) {
            $fields = [];

            // get list of available fields
            foreach ($this->elements as $key=>$f) {
                if ($f instanceof \atk4\data\Field) {
                    $fields[] = $key;
                }
            }
        } else {
            $fields = $this->only_fields;
        }

        $fields2 = [];
        foreach ($fields as $field) {
            if ($this->getElement($field)->never_persist) {
                continue;
            }
            if ($this->getElement($field) instanceof \atk4\data\Expression_SQL) {
                continue;
            }
            $fields2[] = $field;
        }
        $fields = $fields2;

        $subquery = null;
        switch ($mode) {
            case 'select':
                // select but no need your fields
                $query = $this->master_model->action($mode, [false]);
                $query = $this->queryFields($query, array_merge($fields, $this->system_fields));

                $this->addGrouping($query);

                $this->hook('afterGroupSelect', [$query]);

                $this->initQueryConditions($query);

                return $query;

            case 'count':
                $query = $this->master_model->action($mode, $args);

                $query->reset('field')->field(new \atk4\dsql\Expression('"1"'));
                $this->addGrouping($query);

                $this->hook('afterGroupSelect', [$query]);

                $q = $query->dsql();
                $q->table(new \atk4\dsql\Expression("([]) der", [$query]));
                $q->field('count(*)');

                return $q;

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
    function initQueryConditions($q)
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
                            $row[0] = $m->getElement($row[0]);
                        }
                    }
                }

                $q->where($cond[0]);
                continue;
            }

            if (is_string($cond[0])) {
                $cond[0] = $m->getElement($cond[0]);
            }

            if (count($cond) == 2) {
                if ($cond[0] instanceof \atk4\data\Field) {
                    $cond[1] = $this->persistence->typecastSaveField($cond[0], $cond[1]);
                    $q->having($cond[0]->actual ?: $cond[0]->short_name, $cond[1]);
                } else {
                    $q->where($cond[0], $cond[1]);
                }
            } else {
                if ($cond[0] instanceof \atk4\data\Field) {
                    $cond[2] = $this->persistence->typecastSaveField($cond[0], $cond[2]);
                    $q->having($cond[0]->actual ?: $cond[0]->short_name, $cond[1], $cond[2]);
                } else {
                    $q->where($cond[0], $cond[1], $cond[2]);
                }
            }
        }

        return $q;
    }

}
