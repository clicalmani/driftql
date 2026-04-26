<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

class PasswordVerifyBridge extends Bridge
{
    /**
     * Handle the incoming RequestInterface;.
     *
     * @param  \Clicalmani\Foundation\Http\RequestInterface  $request
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

                if (password_verify($request->__dq_vfp_value, $instance->{$request->__dq_vfp_field})) {
                    return response()->json(['valid' => true]);
                }
                
                return response()->json(['valid' => false]);
            } catch (\PDOException $e) {
                return response()->error(app()->environment('production') ? '': $e->getMessage());
            }
        }

        return response()->notFound();
    }
}