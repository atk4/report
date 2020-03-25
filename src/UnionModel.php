<?php
// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\report;

use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\dsql\Expression;

/**
 * UnionModel combines multiple nested models through a UNION in order to retrieve
 * it's value set. The beauty of this class is that it will add fields transparently
 * and will map them appropriately from the nested model if you request
 * those fields from the union model.
 *
 * For example if you are asking sum(amount), there is no need to fetch any extra
 * fields from sub-models.
 */
class UnionModel extends Model
{
    /**
     * UnionModel should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Contain array of array containing model and mappings:
     *
     * $union = [ [ $m1, ['amount'=>'total_gross'] ] , [$m2, []] ];
     *
     * @var array
     */
    public $union = [];

    /**
     * Union normally does not have ID field. Setting this to null will
     * disable various per-id operations, such as load();
     *
     * If you can define unique ID field, you can specify it inside your
     * union model.
     *
     * @var string
     */
    public $id_field = null;

    /**
     * When aggregation happens, this field will contain list of fields
     * we use in groupBy. Multiple fields can be in the array. All
     * the remaining fields will be hidden (marked as system()) and
     * have their "aggregates" added into the selectQuery (if possible).
     *
     * @var array|string
     */
    public $group = null;

    /**
     * When grouping, the functions will be applied as per aggregate
     * fields, e.g. 'balance'=>['sum', 'amount'];
     *
     * You can also use Expression instead of array.
     *
     * @var array
     */
    public $aggregate = [];

    /** @var string Derived table alias */
    public $table = 'derivedTable';

    /**
     * For a sub-model with a specified mapping, return expression
     * that represents a field.
     *
     * @param Model  $model
     * @param string $field
     * @param string $expr
     *
     * @return Field
     */
    public function getFieldExpr(Model $model, $field, $expr = null)
    {
        $field_object = $model->hasField($field);

        if (!$field_object) {
            $field_object = $this->expr('NULL');
        }

        // Some fields are re-mapped for this nested model
        if ($expr !== null) {
            $field_object = $model->expr($expr, [$field_object]);
        }

        return $field_object;
    }

    /**
     * Configures nested models to have a specified set of fields
     * available.
     *
     * @param array $fields
     *
     * @return Expression
     */
    public function getSubQuery($fields) : Expression
    {
        $cnt = 0;
        $expr = [];
        $args = [];

        foreach ($this->union as $n=>list($model, $mapping)) {

            // map fields for related model
            $f = [];
            foreach ($fields as $field) {

                try {
                    // Union can be joined with additional
                    // table/query and we don't touch those
                    // fields

                    if (!$this->hasField($field)) {
                        $field_object = $model->expr('NULL');
                        $f[$field] = $field_object;
                        continue;
                    }

                    if ($this->getField($field)->join || $this->getField($field)->never_persist) {
                        continue;
                    }

                    // Union can have some fields defined as expressions. We don't touch those either.
                    // Imants: I have no idea why this condition was set, but it's limiting our ability
                    // to use expression fields in mapping
                    if ($this->getField($field) instanceof Field_SQL_Expression && !isset($this->aggregate[$field])) {
                        continue;
                    }

                    $field_object = $this->getFieldExpr($model, $field, $mapping[$field] ?? null);

                    if (isset($this->aggregate[$field])) {
                        $field_object = $model->expr($this->aggregate[$field], [$field_object]);
                    }

                    /*
                    if(!isset($model->aggregates_applied)) {
                        $model->aggregates_applied = [];
                    }

                    if($field_object instanceof Field_SQL_Expression && !in_array($field, $model->aggregates_applied))
                    {
                        // generate query

                        // replace original field with query
                        if($model->hasField($field)) {
                            $model->removeField($field);
                        }
                        $field_object = $model->addExpression($field, $field_object);

                        $model->aggregates_applied[] = $field;
                    }
                    */

                    $f[$field] = $field_object;
                } catch (\atk4\core\Exception $e) {
                    throw $e->addMoreInfo('model', $n);
                }
            }

            // now prepare query
            $expr[] = '['.$cnt.']';
            $q = $this->persistence->action($model, 'select', [false]);

            if ($model instanceof UnionModel) {
                $subquery = $model->getSubQuery($fields);
                //$query = parent::action($mode, $args);
                $q->reset('table')->table($subquery);

                if (isset($model->group)) {
                    $q->group($model->group);
                }
            }



            $q->field($f);

            // also for sub-queries
            if ($this->group) {
                if (is_array($this->group)) {
                    foreach ($this->group as $gr) {
                        if (isset($mapping[$gr])) {
                            $q->group($model->expr($mapping[$gr]));
                        } elseif ($f = $model->hasField($gr)) {
                            $q->group($f);
                        }
                    }
                } elseif (isset($mapping[$this->group])) {
                    $q->group($model->expr($mapping[$this->group]));
                } else {
                    $q->group($this->group);
                }
            }
            $args[$cnt++] = $q;
        }
        $args[$cnt] = $this->table;
        return $this->persistence->dsql()->expr('('.join(' UNION ALL ',$expr).') {'.$cnt.'}', $args);
    }

    /**
     * No description.
     *
     * @param string $action
     * @param array  $act_arg
     *
     * @return Expression
     */
    public function getSubAction($action, $act_arg=[]) : Expression
    {
        $cnt = 0;
        $expr = [];
        $args = [];

        foreach ($this->union as list($model, $mapping)) {
            // now prepare query
            $expr[] = '['.$cnt.']';
            if ($act_arg && isset($act_arg[1])) {
                $a = $act_arg;
                $a[1] = $this->getFieldExpr(
                    $model,
                    $a[1],
                    isset($mapping[$a[1]]) ? $mapping[$a[1]] : null
                );
                $q = $model->action($action, $a);
            } else {
                $q = $model->action($action, $act_arg);
            }

            $args[$cnt++] = $q;
        }
        $args[$cnt] = $this->table;
        return $this->persistence->dsql()->expr('('.join(' UNION ALL ',$expr).') {'.$cnt.'}', $args);
    }

    /**
     * No description.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Expression
     */
    public function action($mode, $args = []) : Expression
    {
        switch ($mode) {
            case 'insert':
            case 'update':
            case 'delete':
                throw new Exception(['UnionModel does not support this action', 'action'=>$mode]);
        }

        // get list of available fields
        $fields = $this->only_fields ?: array_keys($this->getFields());
        foreach ($fields as $k=>$field) {
            if ($this->getField($field)->never_persist) {
                unset($fields[$k]);
            }
        }

        $subquery = null;

        switch ($mode) {
            case 'select':
                $subquery = $this->getSubQuery($fields);
                $query = parent::action($mode, $args);
                $query->reset('table')->table($subquery);

                if (isset($this->group)) {
                    $query->group($this->group);
                }
                $this->hook('afterUnionSelect', [$query]);
                return $query;

            case 'count':
                $subquery = $this->getSubAction('count', ['alias'=>'cnt']);
                $query = parent::action('fx', ['sum', $this->expr('{}', ['cnt'])]);
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
                $query = parent::action('fx', [$args[0], $this->expr('{}', ['val'])]);
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
     * Export model.
     *
     * @param array $fields
     *
     * @return array
     */
    public function export($fields = null, $key_field = null, $typecast_data = true)
    {
        if ($fields) {
            $this->onlyFields($fields);
        }

        $data = [];
        foreach ($this->getIterator() as $row) {
            $data[] = $row->get();
        }

        return $data;
    }

    /**
     * Adds nested model in union.
     *
     * @param string|Model $class Model.
     * @param array        $mapping Array of field mapping
     *
     * @return Model
     */
    public function addNestedModel($class, $mapping = []) : Model
    {
        $m = $this->persistence->add($class);
        $this->union[] = [$m, $mapping];

        return $m;
    }

    /**
     * Specify a single field or array of fields.
     *
     * @param string|array $group
     * @param array        $aggregate
     *
     * @return $this
     */
    public function groupBy($group, $aggregate = [])
    {

        $this->aggregate = $aggregate;
        $this->group = $group;

        foreach ($aggregate as $field=>$expr) {

            $field_object = $this->hasField($field);

            $expr = $this->expr($expr, [$field_object]);

            if ($field_object) {
                if (method_exists($this,'removeField')) {
                    $this->removeField($field);
                } else {
                    // compatibility with atk4/data v1
                    $field_object->destroy();
                }
            }

            $field_object = $this->addExpression($field, $expr);
        }

        foreach ($this->union as list($model, $mapping)) {
            if ($model instanceof self) {
                $model->aggregate = $aggregate;
                $model->group = $group;
            }
        }

        return $this;
    }

    /**
     * Adds condition.
     *
     * If UnionModel has such field, then add condition to it.
     * Otherwise adds condition to all nested models.
     *
     * @param string $field
     * @param mixed  $operator
     * @param mixed  $value
     * @param bool   $force_nested Should we add condition to all nested models?
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null, $force_nested = false)
    {
        if (func_num_args() === 1) {
            return parent::addCondition($field);
        }

        // if UnionModel has such field, then add condition to it
        if (($f = $this->hasField($field)) && !$force_nested) {
            return parent::addCondition(...func_get_args());
        }

        // otherwise add condition in all sub-models
        foreach ($this->union as $n => [$model, $mapping]) {
            try {
                $ff = $field;

                if (isset($mapping[$field])) {
                    // field is included in mapping - use mapping expression
                    $ff = $mapping[$field] instanceof Expression
                            ? $mapping[$field]
                            : $this->expr($mapping[$field], $model->getFields());
                } elseif (is_string($field) && $model->hasField($field)) {
                    // model has such field - use that field directly
                    $ff = $model->getField($field);
                } else {
                    // we don't know what to do, so let's do nothing
                    continue;
                }

                switch (func_num_args()) {
                    case 2:
                        $model->addCondition($ff, $operator);
                        break;
                    case 3:
                    case 4:
                        $model->addCondition($ff, $operator, $value);
                        break;
                }
            } catch (\atk4\core\Exception $e) {
                $e->addMoreInfo('sub_model', $n);
                throw $e;
            }
        }

        return $this;
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $arr = [];
        foreach ($this->union as $n => [$model, $mapping]) {
            $arr[get_class($model)] = array_merge(
                ['mapping' => $mapping],
                $model->__debugInfo()
            );
        }

        return array_merge(
            parent::__debugInfo(), [
                'group' => $this->group,
                'aggregate' => $this->aggregate,
                'union_models' => $arr,
            ]
        );
    }

    // }}}
}
