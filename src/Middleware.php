<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Http\Middlewares\Middleware as Base;
use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;

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
     * @param \Clicalmani\Foundation\Http\RequestInterface $request Incoming HTTP request instance.
     * @param \Clicalmani\Foundation\Http\ResponseInterface $response Outgoing HTTP response instance.
     * @param \Closure $next Next middleware handler in the pipeline.
     * @return \Clicalmani\Foundation\Http\ResponseInterface|\Clicalmani\Foundation\Http\RedirectInterface
     */
    public function handle(RequestInterface $request, ResponseInterface $response, \Closure $next) : \Clicalmani\Foundation\Http\ResponseInterface|\Clicalmani\Foundation\Http\RedirectInterface
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