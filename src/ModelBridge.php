<?php 
namespace Tonka\DriftQL;

use App\Http\Requests\DriftQLRequest;
use Clicalmani\Foundation\Acme\Controller;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

class ModelBridge extends Controller
{
    /**
     * Handle the incoming RequestInterface;.
     *
     * @param  \Clicalmani\Foundation\Http\RequestInterface  $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     */
    #[AsValidator(
        model: 'required|dql_model',
        query: 'required|dql_query',
        joins: 'required|dql_joins',
        distinct: 'required|bool',
        by_id: 'bool|sometimes',
        id: 'string|max:100|sometimes',
    )]
    public function __invoke(DriftQLRequest $request) : ResponseInterface
    {
        $config = config('driftql');
        $currentUserRole = auth()->user()->role;
        $requestedModel = "\\App\\Models\\" . $request->model;
        $query = $request->query;
        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $model_instance = new $requestedModel;
        /** @var string */
        $table = $model_instance->getTable();
        /** @var string[] */
        $columns = \Clicalmani\Database\Factory\Schema::getColumnListing($table);

        $columnExists = function(string $column) use($config, $columns) {
            if (!$config['security']['strict_column_check']) return true;
            return in_array($column, $columns);
        };

        $where = true;
        $orders = [];
        $bindings = [];

        if ($request->by_id) {
            $query['wheres'] = [];
            $query['orders'] = [];
            $query['limit'] = 1;

            $where = $model_instance->getKey() . ' = ?';
            $bindings[] = $request->id;
        }

        if (isset($config['policies'][$requestedModel][$currentUserRole])) {
            $policy = $config['policies'][$requestedModel][$currentUserRole];

            if ( is_array($policy) ) {

                // Check policy keys
                if (!isset($policy['column'], $policy['operator'], $policy['value'])) {
                    return response()->error('Invalid policy configuration');
                }

                if (!$columnExists($policy['column'])) {
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

            if (!$columnExists($clause['column'])) {
                return response()->error('Where clause column does not exist in the database schema');
            }

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
        
        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $model_instance = $requestedModel::where($where, $bindings);

        $model_instance->distinct($request->distinct);

        foreach ($request->joins as $join) {
            $model_instance->{$join['type'] . 'Join'}($join['resource'], $join['fkey'], $join['okey']);
        }

        if ( !empty($orders) ) {
            $model_instance->orderBy(join(', ', $orders));
        }

        $model_instance->limit($query['offset'], $query['limit']);
        
        return response()->json($model_instance->fetch()->toArray());
    }
}