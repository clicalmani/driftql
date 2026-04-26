<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

class SelectBridge extends Bridge
{
    /**
     * Handle the incoming RequestInterface;.
     *
     * @param  \Clicalmani\Foundation\Http\RequestInterface  $request
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
        $model_instance = $this->getModel();
        /** @var string */
        $currentUserRole = auth()->user()->role;
        
        $where = true;
        $having = true;
        $orders = [];
        $groups = [];
        $bindings = [];

        if ($request->__dq_by_id) {
            $query['wheres'] = [];
            $query['orders'] = [];
            $query['limit'] = 1;

            $where = $model_instance->getKey() . ' = ?';
            $bindings[] = $request->__dq_id;
        }

        if (isset($config['policies'][$model_instance::class][$currentUserRole])) {
            $policy = $this->getConfig()['policies'][$model_instance::class][$currentUserRole];

            if ( is_array($policy) ) {

                // Check policy keys
                if (!isset($policy['column'], $policy['operator'], $policy['value'])) {
                    return response()->error('Invalid policy configuration');
                }

                if (!$this->columnExists($policy['column'])) {
                    return response()->error('Policy column does not exist in the database schema');
                }

                $value = ($policy['value'] === 'current_user_id') 
                     ? auth()->id() 
                     : $policy['value'];

                $where .= ' AND ' . $policy['column'] . $policy['operator'] . '?';
                $bindings[] = $value;
            }
        }

        foreach ($query['wheres'] as $clause) {

            $where .= ' ' . $clause['boolean'] . ' ' . $clause['column'] . ' ' . $clause['operator'] . ' ?';

            if (strtolower($clause['operator']) === 'in' && is_array($clause['value'])) {
                $placeholders = implode(', ', array_fill(0, count($clause['value']), '?'));
                $where = str_replace('?', "($placeholders)", $where);
                $bindings = array_merge($bindings, $clause['value']);
            } elseif (strtolower($clause['operator']) === 'between' && is_array($clause['value']) && count($clause['value']) === 2) {
                $where = str_replace('?', '? AND ?', $where);
                $bindings = array_merge($bindings, $clause['value']);
            } else {
                $bindings[] = $clause['value'];
            }
        }

        foreach ($query['orders'] as $order) {
            $orders[] = $order['column'] . ' ' . $order['direction'];
        }

        foreach ($query['groups'] as $group) {
            $groups[] = $group['column'] . ' ' . $group['direction'];
        }

        foreach ($query['havings'] as $clause) {

            $having .= ' ' . $clause['boolean'] . ' ' . $clause['column'] . ' ' . $clause['operator'] . ' ';

            if (strtolower($clause['operator']) === 'in' && is_array($clause['value'])) {
                $having .= '(' . collect($clause['value'])->map(fn($v) => '"' . $v . '"')->join() . ')';
            } elseif (strtolower($clause['operator']) === 'between' && is_array($clause['value']) && count($clause['value']) === 2) {
                $having .= $clause['value'][0] . ' AND ' . $clause['value'][1];
            }
        }
        
        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $model_instance = $model_instance::class::where($where, $bindings);

        if ($query['havings']) {
            $model_instance->having($having);
        }

        $model_instance->distinct($request->__dq_distinct);
        
        foreach ($query['joins'] as $join) {
            $model_instance->{$join['type'] . 'Join'}($join['resource'], $join['fkey'], $join['okey']);
        }

        if ( !empty($orders) ) {
            $model_instance->orderBy(join(', ', $orders));
        }

        if ( !empty($groups) ) {
            $model_instance->groupBy(join(', ', $groups));
        }

        $model_instance->limit(@ $query['offset'] ?? 0, @ $query['limit'] ?? 1);
        
        return response()->json($model_instance->fetch()->toArray());
    }
}