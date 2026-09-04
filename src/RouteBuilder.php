<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Support\Facades\Str;
use Clicalmani\Routing\Route;
use Clicalmani\Routing\Segment;
use Clicalmani\Routing\SegmentValidator;
use Inertia\Middleware;

/**
 * Class RouteBuilder
 *
 * Dynamically resolves and constructs DriftQL internal bridge routes based on
 * the incoming client request URL and verb.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
class RouteBuilder extends \Clicalmani\Routing\Builder implements \Clicalmani\Routing\BuilderInterface
{
    /**
     * HTTP verbs allowed by the DriftQL bridge router.
     */
    private const ALLOWED_VERBS = ['post', 'patch', 'delete'];

    /**
     * Client route instance.
     * 
     * @var \Clicalmani\Routing\Route
     */
    private Route $client;

    /**
     * Creates a new Route instance with a given URI.
     *
     * @param string $uri The URI scheme for the route.
     * @return \Clicalmani\Routing\Route
     */
    public function create(string $uri) : \Clicalmani\Routing\Route
    {
        $route = new \Clicalmani\Routing\Route;
        $route->setUri($uri);
        return $route;
    }

    /**
     * Determines whether the current request verb matches any registered DriftQL bridge route scheme.
     *
     * @param string $verb The HTTP verb of the request.
     * @return array<\Clicalmani\Routing\Route> Array containing the configured Route if matched, or an empty array.
     */
    public function matches(string $verb) : array
    {
        if ( ! in_array($verb, self::ALLOWED_VERBS) ) return [];

        $route = $this->getClientRoute();
        $url_scheme = config('driftql.bridge_public_key');

        if ( ! $url_scheme || ! $route || ! str_starts_with(trim(client_url(), '/'), $url_scheme) ) {
            return [];
        }

        $hash = $this->extractHash();
        $action = BridgeAction::fromHash($hash);

        $this->configureRoute($route, $verb, $url_scheme, $hash);
        $this->appendActionSegments($route, $action);

        $route->action = $action?->bridgeClass() ?? SelectBridge::class;

        return [$route];
    }

    /**
     * Extracts and returns the single matching route from an array of matched candidates.
     *
     * @param array<\Clicalmani\Routing\Route> $matches
     * @return \Clicalmani\Routing\Route|null
     */
    public function locate(array $matches) : \Clicalmani\Routing\Route|null
    {
        return array_pop($matches);
    }

    /**
     * Retrieves and resolves the current route matching the client's HTTP request verb.
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

    /**
     * Extracts the SHA-1 action hash from the current client URL segments.
     *
     * @return string The extracted hash, or an empty string if absent.
     */
    private function extractHash() : string
    {
        $segments = preg_split('/\//', client_url(), -1, PREG_SPLIT_NO_EMPTY);
        return $segments[1] ?? '';
    }

    /**
     * Configures a route instance with HTTP verb, base middlewares, and base URI segments.
     *
     * @param Route $route The target Route instance.
     * @param string $verb The HTTP method/verb.
     * @param string $url_scheme The DriftQL bridge public key prefix.
     * @param string $hash The extracted action hash segment.
     * @return void
     */
    private function configureRoute(Route $route, string $verb, string $url_scheme, string $hash) : void
    {
        $route->verb = $verb;
        $route->addMiddleware('web');
        $route->addMiddleware(Middleware::class);

        foreach (array_filter([$url_scheme, $hash]) as $name) {
            $segment = new Segment;
            $segment->name = $name;
            $route->appendSegment($segment);
        }
    }

    /**
     * Appends action-specific URL segments and parameter validations to the route.
     * Currently, only the Delete action requires extra URL parameters (target ID and model).
     *
     * @param Route $route The Route instance to mutate.
     * @param BridgeAction|null $action The resolved bridge action enum.
     * @return void
     */
    private function appendActionSegments(Route $route, ?BridgeAction $action) : void
    {
        if ($action !== BridgeAction::Delete) return;

        $prefix = config('route.parameter_prefix');

        $route->appendSegment($this->makeValidatedSegment(
            segmentName: $prefix . '__dq_id',
            validatorName: '__dq_id',
            value: $_GET['__dq_id'],
            rules: 'required|id|model:' . Str::tableize($_GET['__dq_model'])
        ));

        $route->appendSegment($this->makeValidatedSegment(
            segmentName: $prefix . '__dq_model',
            validatorName: '__dq_model',
            value: $_GET['__dq_model'],
            rules: 'required|dq_model'
        ));
    }

    /**
     * Helper method to build a URL segment coupled with a SegmentValidator.
     *
     * @param string $segmentName Name of the route parameter.
     * @param string $validatorName Validator identifier.
     * @param mixed $value Bound segment value.
     * @param string $rules Validation rules to evaluate against the value.
     * @return Segment
     */
    private function makeValidatedSegment(string $segmentName, string $validatorName, mixed $value, string $rules) : Segment
    {
        $segment = new Segment;
        $segment->name = $segmentName;
        $segment->value = $value;
        $segment->validator = new SegmentValidator($validatorName, $rules);

        return $segment;
    }
}