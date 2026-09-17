<?php
namespace Tonka\DriftQL;

use Clicalmani\Core\Http\Middlewares\Middleware as Base;
use Clicalmani\Core\Http\RequestInterface;
use Clicalmani\Core\Http\ResponseInterface;

/**
 * Class Middleware
 *
 * Handles DriftQL request filtering, configuration state checks, 
 * and user authentication token lifecycle verification.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
class Middleware extends Base 
{
    /**
     * Handle incoming HTTP requests for DriftQL routes.
     * 
     * @param \Clicalmani\Core\Http\RequestInterface $request Incoming HTTP request instance.
     * @param \Clicalmani\Core\Http\ResponseInterface $response Outgoing HTTP response instance.
     * @param \Closure $next Next middleware handler in the pipeline.
     * @return \Clicalmani\Core\Http\ResponseInterface|\Clicalmani\Core\Http\RedirectInterface
     */
    public function handle(RequestInterface $request, ResponseInterface $response, \Closure $next) : \Clicalmani\Core\Http\ResponseInterface|\Clicalmani\Core\Http\RedirectInterface
    {
        if ($config = config('driftql')) {
            // Reject the request if the DriftQL bridge is explicitly disabled
            if ( ! $config['enabled'] ) {
                $response->forbidden();
            }

            if ($user = $request->user()) {
                // Terminate session if user claims authentication but is no longer marked online
                if ($user->isAuthenticated() && false === $user->isOnline()) {
                    $user->destroy();
                    return $response->unauthorized();
                }

                $user->authenticate(); // Renew user authentication token/session

                return $next();
            }
        }

        return $response->unauthorized();
    }

    /**
     * Bootstrap required middleware components and dependencies.
     * 
     * @return void
     */
    public function boot() : void
    {
        $this->include('cookie');
    }
}