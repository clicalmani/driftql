<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Support\Facades\Str;
use Clicalmani\Routing\Route;
use Clicalmani\Routing\Segment;
use Clicalmani\Routing\SegmentValidator;
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
        if ( ! in_array($verb, ['post', 'patch', 'delete']) ) return [];
        
        $route = $this->getClientRoute();
        $url_scheme = config('driftql.bridge_public_key');
        
        if ($url_scheme && $route && str_starts_with(trim(client_url(), '/'), $url_scheme)) {
            $route->verb = $verb;
            $route->addMiddleware('web');
            $route->addMiddleware(Middleware::class);

            $seg_names = [$url_scheme];
            $arr = preg_split('/\//', client_url(), -1, PREG_SPLIT_NO_EMPTY);
            
            if ($hash = @$arr[1] ?? '') {
                $seg_names[] = $hash;
            }

            foreach ($seg_names as $name) {
                $segment = new Segment;
                $segment->name = $name;
                $route->appendSegment($segment);
            }
            
            if (hash_equals($hash, sha1('store')) || hash_equals($hash, sha1('update'))) {
                $route->action = WriteBridge::class;
            } elseif (hash_equals($hash, sha1('delete'))) {
                // ID Segment
                $segment = new Segment;
                $segment->name = config('route.parameter_prefix') . '__dq_id';
                $segment->value = $_GET['__dq_id'];
                $segment->validator = new SegmentValidator('__dq_id', 'required|id|model:' . Str::tableize($_GET['__dq_model']));
                $route->appendSegment($segment);

                // Model segment
                $segment = new Segment;
                $segment->name = config('route.parameter_prefix') . '__dq_model';
                $segment->value = $_GET['__dq_model'];
                $segment->validator = new SegmentValidator('__dq_model', 'required|dq_model');
                $route->appendSegment($segment);

                $route->action = DestroyBridge::class;
            } elseif (hash_equals($hash, sha1('verify_password'))) {
                $route->action = PasswordVerifyBridge::class;
            } else {
                $route->action = SelectBridge::class;
            }
            
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
