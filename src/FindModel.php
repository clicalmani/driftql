<?php
namespace Tonka\DriftQL;

use Clicalmani\Core\Filesystem\DirectoryScanner;

/**
 * Trait FindModel
 *
 * Provides model resolution capabilities by scanning application directory structures
 * to locate class names matching the requested model parameter.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
trait FindModel
{
    /**
     * Cache for resolved model class map.
     *
     * @var array<string, string>
     */
    private array $resolvedModels = [];

    /**
     * Resolves the fully qualified class name for the requested model from the incoming request.
     *
     * @return string Fully qualified class name of the target model.
     * @throws \Exception If no matching class is found in the Models directory.
     */
    protected function getRequestedModel(): string
    {
        $key = request()->input('__dq_model');
        $modelClass = config("driftql.whitelist.allowed_models.{$key}");

        if (array_key_exists($key, $this->resolvedModels)) {
            return $this->resolvedModels[$key];
        }

        if ($modelClass === null) {
            throw new \Exception("Model index '{$key}' is not allowed or not configured in the whitelist.");
        }

        return $this->resolvedModels[$key] = $modelClass;
    }
}