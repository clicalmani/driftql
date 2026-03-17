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
        
        if ($session_id = $request->cookie()->get('_SESSION_COOKIE')) {
            $session = new \Clicalmani\Foundation\Http\Session\DBSessionHandler(false, ['driver' => 'mysql', 'table' => env('DB_TABLE_PREFIX') . 'sessions']);
            $session->open('/', $session_id);
            
            if ($session->validate_sid($session_id)) return $next();
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
