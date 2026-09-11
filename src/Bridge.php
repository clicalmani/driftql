<?php 
namespace Tonka\DriftQL;

use Clicalmani\Database\Factory\Models\ModelInterface;
use Clicalmani\Foundation\Acme\Controller;
use Clicalmani\Foundation\Http\Request;
use Clicalmani\Foundation\Http\RequestInterface;
use Tonka\DriftQL\Exceptions\DriftQLException;

/**
 * Class Bridge
 *
 * Base controller for DriftQL bridge interactions. Handles configuration loading,
 * model instantiation, authorization policy enforcement, and schema validation.
 *
 * @package Tonka\DriftQL
 * @package clicalmani
 */
class Bridge extends Controller
{
    use FindModel;
    
    /**
     * Retrieves the DriftQL module configuration array.
     *
     * @return array Configuration options for DriftQL.
     */
    protected function getConfig(): array
    {
        return config('driftql');
    }

    /**
     * Resolves and instantiates the target model, enforcing authorization policy checks.
     *
     * @return ModelInterface|null An instance of the requested model interface.
     * @throws DriftQLException If authorization fails for the model policy.
     */
    protected function getModel(): ModelInterface
    {
        $modelClass = $this->getRequestedModel();

        if ($id = request()->input('__dq_id')) {
            if ($instance = $modelClass::find(request()->input('__dq_id'))) {
                return $instance;
            }

            throw new DriftQLException("Model " . $modelClass . " with ID " . $id . " not found.");
        } 
        
        return new $modelClass;
    }

    /**
     * Resolves, evaluates, and initializes the security or authorization policy 
     * associated with the requested model and optional action.
     *
     * @param string|null $action Optional bridge action name to check policy against.
     * @return RequestInterface|null The evaluated request policy instance, or null if no policy is configured.
     * @throws DriftQLException If the resolved policy is invalid or missing for the specified action.
     */
    protected function getPolicy(?string $action = null): ?RequestInterface
    {
        $policies = config('driftql.policies', []);
        
        if ($modelClass = $this->getRequestedModel()) {
            if (isset($policies[$modelClass])) {
                $policy = $policies[$modelClass];
                
                if ( isset($action) ) {
                    if ( is_array($policy) ) {
                        if ( isset($policy[$action]) ) {

                            $policy = $policy[$action];
                            
                            if ( is_subclass_of($policy, \Clicalmani\Foundation\Http\RequestInterface::class) ) {
                                $policy = new $policy;
                                
                                $policy->make(request()->all());
                                $policy->authorize();
                                $policy->prepareForValidation();
                                $policy->signatures();
                                Request::current($policy);
                                
                                return $policy;
                            }

                            if ( (is_subclass_of($policy, \Clicalmani\Foundation\Auth\Contract::class) && !(new $policy)->authorize()) ) {
                                throw new DriftQLException("Unauthorized access to model " . $this->getRequestedModel());
                            }
                        } else throw new DriftQLException(sprintf("Model %s does not have a policy for action %s", $modelClass, $action));
                    }
                }
            }
        }

        throw new DriftQLException(
            sprintf("Policy for model %s must be a subclass of %s or %s", $model::class, 
                \Clicalmani\Foundation\Http\Request::class, Clicalmani\Foundation\Auth\Contract::class)
        );
    }

    /**
     * Verifies whether a given column exists in the target model's database schema.
     *
     * @param string $column The column name to verify.
     * @return bool True if the column exists or strict checking is disabled, false otherwise.
     */
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