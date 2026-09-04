<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

/**
 * Class SelectBridge
 *
 * Handles select query execution over the DriftQL bridge layer.
 *
 * @package Tonka\DriftQL
 * @author  Clicalmani
 */
class SelectBridge extends Bridge
{
    /**
     * Handle the incoming request.
     *
     * @param \Clicalmani\Foundation\Http\RequestInterface $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     */
    #[AsValidator(
        __dq_model: 'required|dql_model',
        __dq_query: 'required|dql_query',
        __dq_distinct: 'required|bool',
        __dq_by_id: 'bool|sometimes',
        __dq_id: 'string|max:100|sometimes',
    )]
    public function __invoke(RequestInterface $request) : ResponseInterface
    {
        $query = $request->__dq_query;
        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $modelInstance = $this->getModel();

        if ($request->__dq_by_id) {
            $query = $this->applyByIdConstraints($query);
        }

        $whereResult = $this->buildWhereClause($request, $query, $modelInstance);
        if ($whereResult instanceof ResponseInterface) {
            return $whereResult;
        }
        [$where, $bindings] = $whereResult;

        $having = $this->buildHavingClause($query['havings']);

        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $builder = $modelInstance::class::where($where, $bindings);

        if ($query['havings']) {
            $builder->having($having);
        }

        $builder->distinct($request->__dq_distinct);

        $this->applyJoins($builder, $query['joins']);
        $this->applyOrderBy($builder, $query['orders']);
        $this->applyGroupBy($builder, $query['groups']);
        $this->applyWith($builder, $query['withs'] ?? []);

        return $this->executeQuery($builder, $query);
    }

    /**
     * Restricts the query to a default order and a limit of 1 when
     * searching for a specific record by its ID.
     *
     * @param array $query The original query array structure.
     * @return array The mutated query array.
     */
    private function applyByIdConstraints(array $query) : array
    {
        $query['orders'] = [];
        $query['limit'] = 1;

        return $query;
    }

    /**
     * Builds the complete WHERE clause (base + policy + query conditions) and its bindings.
     * Returns an error ResponseInterface if the configured policy is invalid.
     *
     * @param RequestInterface $request
     * @param array $query
     * @param mixed $modelInstance
     * @return array|ResponseInterface Returns [string $where, array $bindings] or an error ResponseInterface.
     */
    private function buildWhereClause(RequestInterface $request, array $query, $modelInstance) : array|ResponseInterface
    {
        [$where, $bindings] = $this->buildBaseWhere($request, $modelInstance);

        $policyResult = $this->applyPolicy($modelInstance, $where, $bindings);
        if ($policyResult instanceof ResponseInterface) {
            return $policyResult;
        }
        [$where, $bindings] = $policyResult;

        if ( ! $request->__dq_by_id) {
            foreach ($query['wheres'] as $clause) {
                [$sql, $clauseBindings] = $this->buildConditionSegment($clause);
                $where .= ' ' . $clause['boolean'] . ' ' . $sql;
                $bindings = array_merge($bindings, $clauseBindings);
            }
        }

        return [$where, $bindings];
    }

    /**
     * Generates the base WHERE clause: primary key equality if `__dq_by_id` is set,
     * otherwise `true` as a neutral starting point for subsequent concatenations.
     *
     * @param RequestInterface $request
     * @param mixed $modelInstance
     * @return array Containing SQL string and bindings array.
     */
    private function buildBaseWhere(RequestInterface $request, $modelInstance) : array
    {
        if ($request->__dq_by_id) {
            return [
                $modelInstance->getKey()->scalarName() . ' = ?',
                [$request->__dq_id]
            ];
        }

        return [true, []];
    }

    /**
     * Applies the configured security policy for the current model/role to the WHERE clause.
     *
     * @param mixed $modelInstance
     * @param mixed $where
     * @param array $bindings
     * @return array|ResponseInterface Updated [where, bindings] or an error ResponseInterface.
     */
    private function applyPolicy($modelInstance, mixed $where, array $bindings) : array|ResponseInterface
    {
        $currentUserRole = auth()?->role ?? null;
        $policy = $this->getConfig()['policies'][$modelInstance::class][$currentUserRole] ?? null;

        if ( ! is_array($policy) ) {
            return [$where, $bindings];
        }

        if ( ! isset($policy['column'], $policy['operator'], $policy['value']) ) {
            return response()->error('Invalid policy configuration');
        }

        if ( ! $this->columnExists($policy['column']) ) {
            return response()->error('Policy column does not exist in the database schema');
        }

        $value = $policy['value'] === 'current_user_id'
            ? auth()->id()
            : $policy['value'];

        $where .= ' AND ' . $policy['column'] . $policy['operator'] . '?';
        $bindings[] = $value;

        return [$where, $bindings];
    }

    /**
     * Builds the SQL fragment and bindings for a single WHERE condition,
     * handling special operators like IN and BETWEEN locally.
     *
     * @param array $clause Condition details (column, operator, value, boolean).
     * @return array Tuple containing [string $sqlSegment, array $bindings].
     */
    private function buildConditionSegment(array $clause) : array
    {
        $sql = $clause['column'] . ' ' . $clause['operator'] . ' ?';
        $operator = strtolower($clause['operator']);

        if ($operator === 'in' && is_array($clause['value'])) {
            $placeholders = implode(', ', array_fill(0, count($clause['value']), '?'));
            return [str_replace('?', "($placeholders)", $sql), $clause['value']];
        }

        if ($operator === 'between' && is_array($clause['value']) && count($clause['value']) === 2) {
            return [str_replace('?', '? AND ?', $sql), $clause['value']];
        }

        return [$sql, [$clause['value']]];
    }

    /**
     * Builds the HAVING clause string with inline values.
     *
     * @param array $havings Array of having conditions.
     * @return string The raw SQL HAVING condition string.
     */
    private function buildHavingClause(array $havings) : string
    {
        $having = true;

        foreach ($havings as $clause) {
            $having .= ' ' . $clause['boolean'] . ' ' . $clause['column'] . ' ' . $clause['operator'] . ' ';
            $operator = strtolower($clause['operator']);

            if ($operator === 'in' && is_array($clause['value'])) {
                $having .= '(' . collect($clause['value'])->map(fn($v) => '"' . $v . '"')->join() . ')';
            } elseif ($operator === 'between' && is_array($clause['value']) && count($clause['value']) === 2) {
                $having .= $clause['value'][0] . ' AND ' . $clause['value'][1];
            }
        }

        return $having;
    }

    /**
     * Applies requested table joins to the query builder.
     *
     * @param mixed $builder Query builder instance.
     * @param array $joins Array of join definitions.
     * @return void
     */
    private function applyJoins($builder, array $joins) : void
    {
        foreach ($joins as $join) {
            $builder->{$join['type'] . 'Join'}($join['resource'], $join['fkey'], $join['okey']);
        }
    }

    /**
     * Applies ORDER BY directives to the query builder.
     *
     * @param mixed $builder Query builder instance.
     * @param array $orders Array of order clauses.
     * @return void
     */
    private function applyOrderBy($builder, array $orders) : void
    {
        if (empty($orders)) return;

        $columns = array_map(
            fn($order) => $order['column'] . ' ' . $order['direction'],
            $orders
        );

        $builder->orderBy(join(', ', $columns));
    }

    /**
     * Applies GROUP BY directives to the query builder.
     *
     * @param mixed $builder Query builder instance.
     * @param array $groups Array of group clauses.
     * @return void
     */
    private function applyGroupBy($builder, array $groups) : void
    {
        if (empty($groups)) return;

        $columns = array_map(
            fn($group) => $group['column'] . ' ' . $group['direction'],
            $groups
        );

        $builder->groupBy(join(', ', $columns));
    }

    /**
     * Applies eager loading relations (WITH) to the query builder.
     *
     * @param mixed $builder Query builder instance.
     * @param array $withs Relations to eager load.
     * @return void
     */
    private function applyWith($builder, array $withs) : void
    {
        if (empty($withs)) return;

        $builder->with($withs);
    }

    /**
     * Configures pagination offset and limit on the query builder.
     *
     * @param mixed $builder Query builder instance.
     * @param int|null $page Target page number.
     * @param int|null $perPage Items per page.
     * @return void
     */
    private function applyPagination($builder, ?int $page = 1, ?int $perPage = 1) : void
    {
        $offset = ($page - 1) * $perPage;
        $builder->limit($offset, $perPage);
    }

    /**
     * Executes the built query and returns the JSON response,
     * or an error response in case of an exception.
     *
     * @param mixed $builder Query builder instance.
     * @param array $query Original query parameter structure.
     * @return ResponseInterface
     */
    private function executeQuery($builder, array $query) : ResponseInterface
    {
        try {
            $builder->limit($query['offset'] ?? 0, $query['limit'] ?? 1);

            $data = $builder->get();

            if (isset($query['paginate']) && $query['paginate']) {
                $queryBuilder = $builder->getBuilder();
                return response()->json([
                    'data' => $data,
                    'pagination' => [
                        'page'       => $query['offset'] ?? 0,
                        'perPage'    => $query['limit'] ?? 1,
                        'total'      => $queryBuilder->numRows(),
                        'totalPages' => ceil($queryBuilder->rowCount() / ($query['limit'] ?? 1))
                    ]
                ]);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->error(app()->environment('production') ? '' : $e->getMessage());
        }
    }
}