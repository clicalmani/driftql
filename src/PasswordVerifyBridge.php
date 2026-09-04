<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

/**
 * Class PasswordVerifyBridge
 *
 * Handles password verification requests over the DriftQL bridge layer.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
class PasswordVerifyBridge extends Bridge
{
    /**
     * Handle the incoming password verification request.
     *
     * @param \Clicalmani\Foundation\Http\RequestInterface $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     */
    #[AsValidator(
        __dq_model: 'required|dql_model'
    )]
    public function __invoke(RequestInterface $request) : ResponseInterface
    {
        if ($policy = $this->getPolicy('password_verify')) {
            if (!$policy->authorize()) {
                return response()->forbidden();
            }

            try {
                /** @var \Clicalmani\Database\Factory\Models\Elegant */
                $instance = $this->getModel();

                // Compare the plain password against the hashed field on the model
                if (password_verify($request->__dq_vfp_value, $instance->{$request->__dq_vfp_field})) {
                    return response()->json(['valid' => true]);
                }
                
                return response()->json(['valid' => false]);
            } catch (\PDOException $e) {
                return response()->error(app()->environment('production') ? '' : $e->getMessage());
            }
        }

        return response()->notFound();
    }
}