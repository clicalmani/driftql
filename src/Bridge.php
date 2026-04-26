<?php 
namespace Tonka\DriftQL;

use Clicalmani\Database\Factory\Models\ModelInterface;
use Clicalmani\Foundation\Acme\Controller;
use Clicalmani\Foundation\Http\Request;
use Clicalmani\Foundation\Http\RequestInterface;
use Tonka\DriftQL\Exceptions\DriftQLException;

class Bridge extends Controller
{
    protected function getConfig(): array
    {
        return config('driftql');
    }

    protected function getModel(): ?ModelInterface
    {
        if ($model = request()->input('__dq_model')) {
            $model = "\\App\\Models\\" . $model;

            if ($id = request()->input('__dq_id')) {
                return $model::find($id);
            }

            return new $model;
        }

        return null;
    }

    protected function getPolicy(?string $action = null): ?RequestInterface
    {
        $policies = config('driftql.policies', []);

        if ($model = $this->getModel()) {
            if (isset($policies[$model::class])) {
                $policy = $policies[$model::class];

                if ( isset($action) ) {
                    if ( is_array($policy) ) {
                        if ( isset($policy[$action]) ) {

                            $policy = $policy[$action];

                            if ( ! is_subclass_of($policy, \Clicalmani\Foundation\Http\Request::class) ) {
                                throw new DriftQLException(sprintf("Policy for model %s must be a subclass of Clicalmani\Foundation\Http\Request", $model::class));
                            }
                        } else throw new DriftQLException(sprintf("Policy for model %s does not have a policy for action %s", $model::class, $action));
                    }
                } 

                $policy = new $policy;

                $policy->prepareForValidation();
                $policy->signatures();
                $policy->validate();
                Request::current($policy);
                
                return Request::current();
            }
        }

        return null;
    }

    protected function columnExists(string $column): bool
    {
        /** @var \Clicalmani\Database\Factory\Models\Elegant */
        $model_instance = $this->getModel();
        /** @var string */
        $table = $model_instance->getTable();
        /** @var string[] */
        $columns = \Clicalmani\Database\Factory\Schema::getColumnListing($table);

        if (!$this->getConfig()['security']['strict_column_check']) return true;
        return in_array($column, $columns);
    }
}