<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\Request;
use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

class DestroyBridge extends Bridge
{
    /**
     * Handle the incoming RequestInterface;.
     *
     * @param  \Clicalmani\Foundation\Http\RequestInterface  $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     */
    #[AsValidator(
        __dq_id: 'required|numeric|min:1',
        __dq_model: 'required|dql_model'
    )]
    public function __invoke(RequestInterface $request) : ResponseInterface
    {
        if ($policy = $this->getPolicy('destroy')) {
            if (!$policy->authorize()) {
                return response()->forbidden();
            }

            try {
                /** @var \Clicalmani\Database\Factory\Models\Elegant */
                $instance = $this->getModel();
                return response()->success($instance->delete());
            } catch (\PDOException $e) {
                return response()->error(app()->environment('production') ? '': $e->getMessage());
            }
        }

        return response()->notFound();
    }
}