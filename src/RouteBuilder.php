<?php
namespace Tonka\DriftQL;

use Clicalmani\Routing\Route;
use Inertia\Middleware;

class RouteBuilder extends \Clicalmani\Routing\Builder implements \Clicalmani\Routing\BuilderInterface
{
    /**
     * Client route
     * 
     * @var \Clicalmani\Routing\Route
     */
    private Route $client;

    /**
     * Create a new route.
     * 
     * @param string $uri Route uri
     * @return \Clicalmani\Routing\Route
     */
    public function create(string $uri) : \Clicalmani\Routing\Route
    {
        $route = new \Clicalmani\Routing\Route;
        $route->setUri($uri);
        return $route;
    }
    
    /**
     * Match candidate routes.
     * 
     * @param string $verb
     * @return \Clicalmani\Routing\Route[] 
     */
    public function matches(string $verb) : array
    {
        if ('post' !== $verb) return [];
        
        $this->client = $this->getClientRoute();
        $url_scheme = config('driftql.bridge_public_key');
        
        if ($url_scheme === trim(urldecode(client_uri()), '/')) {
            $route = $this->create($url_scheme);
            $route->addMiddleware('web');
            $route->addMiddleware(Middleware::class);
            $route->action = ModelBridge::class;
            
            return [$route];
        }

        return [];
    }

    /**
     * Locate the current route in the candidate routes list.
     * 
     * @param \Clicalmani\Routing\Route[] $matches
     * @return \Clicalmani\Routing\Route|null
     */
    public function locate(array $matches) : \Clicalmani\Routing\Route|null
    {
        return array_pop($matches);
    }

    /**
     * Build the requested route. 
     * 
     * @return \Clicalmani\Routing\Route|null
     */
    public function getRoute() : \Clicalmani\Routing\Route|null
    {
        return $this->locate(
            $this->matches( 
                \Clicalmani\Foundation\Support\Facades\Route::getClientVerb()
            ) 
        );
    }
}
