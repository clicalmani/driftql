<?php 
namespace Tonka\DriftQL;

use Clicalmani\Database\Factory\Models\ModelInterface;
use Clicalmani\Core\Acme\Controller;
use Clicalmani\Core\Http\Request;
use Clicalmani\Core\Http\RequestInterface;
use Clicalmani\Database\Factory\Models\Elegant;
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
        $modelClass = $this->getRequestedModel();
        $policy = $policies[$modelClass] ?? null;
        $actionName = $action ?? 'UNKNOW';

        if (null !== $policy) {
            $policy = is_array($policy) ? $policy[$actionName]: $policy;

            if ( (is_subclass_of($policy, \Clicalmani\Core\Auth\Contract::class) && !(new $policy)->authorize()) ) {
                throw new DriftQLException("Unauthorized access to model " . $this->getRequestedModel());
            }
                    
            if ( is_subclass_of($policy, \Clicalmani\Core\Http\RequestInterface::class) ) {
                $policy = new $policy;
                
                $policy->make(request()->all());
                $policy->authorize();
                $policy->prepareForValidation();
                $policy->signatures();
                Request::current($policy);
                
                return $policy;
            }
        } else throw new DriftQLException(sprintf("Model %s does not have a policy for action %s", $modelClass, $action));

        throw new DriftQLException(
            sprintf("Policy for model %s must be a subclass of %s or %s; %s given", $modelClass, 
                \Clicalmani\Core\Http\Request::class, Clicalmani\Core\Auth\Contract::class, $policy)
        );
    }

    /**
     * Verifies whether a given column exists in the target model's database schema.
     *
     * @param string $column The column name to verify.
     * @return bool True if the column exists or strict checking is disabled, false otherwise.
     */
    protected function columnExists(string $column, ?Elegant $model = null): bool
    {
        /** @var Elegant */
        $model ??= $this->getModel();
        /** @var \Clicalmani\Database\Factory\Entity */
        $entity = $model->getEntity();

        $attribute = $entity->getAttribute($column);

        if ($attribute->isHidden()) {
            return false;
        }
        
        return $entity->hasAttribute($column);
    }
}