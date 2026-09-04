<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Filesystem\DirectoryScanner;

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
        $name = request()->input('__dq_model');
        if (array_key_exists($name, $this->resolvedModels)) {
            return $this->resolvedModels[$name];
        }

        $matches = (new DirectoryScanner(
            rootPath: app()->appPath('Models'),
            baseNamespace: 'App\\Models',
            ignore: ['.', '..']
        ))->discoverClasses(
            fn(string $class) => class_basename($class) === $name
        );
        
        if (empty($matches)) {
            throw new \Exception("Model '$name' not found in the application.");
        }

        return $this->resolvedModels[$name] = $matches[0];
    }
}