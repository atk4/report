<?php

declare(strict_types=1);

namespace atk4\report;

use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\data\Reference;
use atk4\dsql\Query;

/**
 * GroupModel allows you to query using "group by" clause on your existing model.
 * It's quite simple to set up.
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
 * $gr->addField('middle');
 *
 * If this field exist in the original model it will be added and you'll get exception otherwise. Finally you are
 * permitted to add expressions.
 *
 * The base model must not be UnionModel or another GroupModel, however it's possible to use GroupModel as nestedModel inside UnionModel.
 * UnionModel implements identical grouping rule on its own.
 *
 * You can also pass seed (for example field type) when aggregating:
 * $gr->groupBy(['first','last'], ['salary' => ['sum([])', 'type'=>'money']];
 */
class GroupModel extends Model
{
    /** @const string */
    public const HOOK_AFTER_GROUP_SELECT = self::class . '@afterGroupSelect';

    /**
     * GroupModel should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /** @var Model */
    public $master_model;

    /** @var string */
    public $id_field;

    /** @var array */
    public $group = [];

    /** @var array */
    public $aggregate = [];

    /** @var array */
    public $system_fields = [];

    /**
     * Constructor.
     */
    public function __construct(Model $model, array $defaults = [])
    {
        $this->master_model = $model;
        $this->table = $model->table;

        //$this->_default_class_addExpression = $model->_default_class_addExpression;
        parent::__construct($model->persistence, $defaults);

        // always use table prefixes for this model
        $this->persistence_data['use_table_prefixes'] = true;
    }

    /**
     * Specify a single field or array of fields on which we will group model.
     *
     * @param array $group     Array of field names
     * @param array $aggregate Array of aggregate mapping
     *
     * @return $this
     */
    public function groupBy(array $group, array $aggregate = [])
    {
        $this->group = $group;
        $this->aggregate = $aggregate;

        $this->system_fields = array_unique($this->system_fields + $group);
        foreach ($group as $field) {
            $this->addField($field);
        }

        foreach ($aggregate as $field => $expr) {
            $seed = is_array($expr) ? $expr : [$expr];

            // field originally defined in the parent model
            if ($this->master_model->hasField($field)) {
                $field_object = $this->master_model->getField($field);
            } else {
                $field_object = null;
            }

            // can be used as part of expression
            $seed[0] = $this->master_model->expr($seed[0], [$field_object]);

            // now add the expressions here
            $field_object = $this->addExpression($field, $seed);
        }

        return $this;
    }

    /**
     * Return reference field.
     *
     * @param string $link
     */
    public function getRef($link): Reference
    {
        return $this->master_model->getRef($link);
    }

    /**
     * Adds new field into model.
     *
     * @param string       $name
     * @param array|object $seed
     *
     * @return Field
     */
    public function addField($name, $seed = [])
    {
        if (!is_array($seed)) {
            $seed = [$seed];
        }

        if (
            isset($seed[0]) && $seed[0] instanceof Field_SQL_Expression
            || isset($seed['never_persist']) && $seed['never_persist']
        ) {
            return parent::addField($name, $seed);
        }

        if ($this->master_model->hasField($name)) {
            $field = $this->master_model->getField($name);
        }

        return parent::addField($name, $field ? array_merge([$field], $seed) : $seed);
    }

    /**
     * Given a query, will add safe fields in.
     */
    public function queryFields(Query $query, array $fields = []): Query
    {
        $this->persistence->initQueryFields($this, $query, $fields);

        return $query;
    }

    /**
     * Adds grouping in query.
     */
    public function addGrouping(Query $query)
    {
        // use table alias of master model
        $this->table_alias = $this->master_model->table_alias;

        foreach ($this->group as $field) {
            if ($this->master_model->hasField($field)) {
                $el = $this->master_model->getField($field);
            }
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
     * @param int      $count
     * @param int|null $offset
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
     * @param mixed     $field
     * @param bool|null $desc
     *
     * @return $this
     *
     * @todo Incorrect implementation
     */
    public function setOrder($field, $desc = null)
    {
        $this->master_model->setOrder($field, $desc);

        return $this;
    }

    /**
     * Execute action.
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
                throw new Exception(['GroupModel does not support this action', 'action' => $mode]);
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

                $this->hook(self::HOOK_AFTER_GROUP_SELECT, [$query]);

                return $query;
            case 'count':
                $query = $this->master_model->action($mode, $args);

                $query->reset('field')->field($this->expr('1'));
                $this->addGrouping($query);

                $this->hook(self::HOOK_AFTER_GROUP_SELECT, [$query]);

                $q = $query->dsql();
                $q->table($this->expr('([]) der', [$query]));
                $q->field('count(*)');

                return $q;
            case 'field':
                if (!is_string($args[0])) {
                    throw new Exception(['action(field) only support string fields', 'field' => $args[0]]);
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

                $subquery = $this->getSubAction('fx', [$args[0], $args[1], 'alias' => 'val']);

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
     * Our own way applying conditions, where we use "having" for
     * fields.
     */
    public function initQueryConditions(Query $q): Query
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

                $q->having($cond[0]);

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
                    $q->having($cond[0], $cond[1]);
                }
            } else {
                if ($cond[0] instanceof Field) {
                    $cond[2] = $this->persistence->typecastSaveField($cond[0], $cond[2]);
                    $q->having($cond[0]->actual ?: $cond[0]->short_name, $cond[1], $cond[2]);
                } else {
                    $q->having($cond[0], $cond[1], $cond[2]);
                }
            }
        }

        return $q;
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), [
            'group' => $this->group,
            'aggregate' => $this->aggregate,
            'master_model' => $this->master_model->__debugInfo(),
        ]);
    }

    // }}}
}
