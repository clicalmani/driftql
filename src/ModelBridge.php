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
        query: 'required|dql_query'
    )]
    public function __invoke(DriftQLRequest $request) : ResponseInterface
    {
        $config = DriftQLServiceProvider::getConfig();
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
        $bindings = [];

        if (isset($config['policies'][$requestedModel][$currentUserRole])) {
            $policy = $config['policies'][$requestedModel][$currentUserRole];

            if ($policy !== null) {

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

            if (!$columnExists($policy['column'])) {
                return response()->error('Where clause column does not exist in the database schema');
            }

            $where .= ' ' . $clause['boolean'] . ' ' . $clause['column'] . $clause['operator'] . '?';
            $bindings[] = $clause['value'];
        }
        
        $model_instance->limit($query['offset'], $query['limit']);
        $model_instance->where($where, $bindings);

        return response()->json($model_instance->fetch());
    }
}