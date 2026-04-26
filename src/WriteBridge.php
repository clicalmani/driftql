<?php 
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Clicalmani\Validation\AsValidator;

class WriteBridge extends Bridge
{
    /**
     * Handle the incoming Request
     *
     * @param  \Clicalmani\Foundation\Http\RequestInterface  $request
     * @return \Clicalmani\Foundation\Http\ResponseInterface
     */
    #[AsValidator(
        __dq_model: 'required|dql_model'
    )]
    public function __invoke(RequestInterface $request) : ResponseInterface
    {
        if ($policy = $this->getPolicy($request->__dq_id ? 'update': 'store')) {
            if (!$policy->authorize()) {
                return response()->forbidden();
            }

            try {
                /** @var \Clicalmani\Database\Factory\Models\Elegant */
                $instance = $this->getModel();
                $instance->swap();
                $instance->save();
                return response()->success($instance);
            } catch (\PDOException $e) {
                return response()->error(app()->environment('production') ? '': $e->getMessage());
            }
        }

        return response()->notFound();
    }
}