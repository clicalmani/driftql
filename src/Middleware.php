<?php
namespace App\Http\Middlewares;

use Clicalmani\Foundation\Http\Middlewares\Middleware as Base;
use Clicalmani\Foundation\Http\RequestInterface;
use Clicalmani\Foundation\Http\ResponseInterface;
use Tonka\DriftQL\DriftQLServiceProvider;

class Middleware extends Base 
{
    /**
     * Handler
     * 
     * @param \Clicalmani\Foundation\Http\Requests\RequestInterface $request Request object
     * @param \Clicalmani\Foundation\Http\ResponseInterface $response Response object
     * @param \Closure $next Next middleware function
     * @return \Clicalmani\Foundation\Http\ResponseInterface|\Clicalmani\Foundation\Http\RedirectInterface
     */
    public function handle(RequestInterface $request, ResponseInterface $response, \Closure $next) : \Clicalmani\Foundation\Http\ResponseInterface|\Clicalmani\Foundation\Http\RedirectInterface
    {
        $config = DriftQLServiceProvider::getConfig();

        if ( ! $config['enabled'] ) $response->forbiden();

        if ($user = $request->user()) {
            if ($user->isAuthenticated() && false === $user->isOnline()) {
                $user->destroy();
                return $response->unauthorized();
            }

            $user->authenticate(); // Renew user authentication

            return $next();
        }

        return $response->unauthorized();
    }

    /**
     * Bootstrap
     * 
     * @return void
     */
    public function boot() : void
    {
        $this->include('cookie');
    }
}
