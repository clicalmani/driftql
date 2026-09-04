<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Foundation\Support\Facades\DB;
use Clicalmani\Validation\AsValidator;

/**
 * Class WriteBridge
 *
 * Handles resource creation (store) and modification (update) requests 
 * within a database transaction over the DriftQL bridge layer.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
class WriteBridge extends Bridge
{
    /**
     * Handle the incoming write request (store or update).
     *
     * @param \Clicalmani\Foundation\Http\RequestInterface $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     * @throws \Exception Re-throws any exception caught during transaction execution.
     */
    #[AsValidator(
        __dq_model: 'required|dql_model'
    )]
    public function __invoke(RequestInterface $request) : ResponseInterface
    {
        // Dynamically resolve policy action based on presence of resource ID
        if ($policy = $this->getPolicy($request->__dq_id ? 'update' : 'store')) {
            if (!$policy->authorize()) {
                return response()->forbidden();
            }

            return DB::transaction(function() {
                try {
                    /** @var \Clicalmani\Database\Factory\Models\Elegant */
                    $instance = $this->getModel();
                    
                    // Populate model properties from request payload
                    $instance->swap();
                    
                    // Persist model changes to the database
                    $instance->save();
                    
                    return response()->success($instance);
                } catch (\Exception $e) {
                    throw $e;
                }
            });
        }

        return response()->notFound();
    }
}