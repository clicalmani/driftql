<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Providers\ServiceProvider;

/**
 * Class DriftQLServiceProvider
 *
 * Bootstraps and registers DriftQL core components, custom validation rules, 
 * console commands, and configuration settings within the framework lifecycle.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
class DriftQLServiceProvider extends ServiceProvider
{
    /**
     * Register services, validation rules, and CLI console commands.
     *
     * @return void
     */
    public function register(): void
    {
        parent::register();

        // Register DriftQL-specific validation rules
        foreach ([Rules\DriftQLModelRule::class, Rules\DriftQLQueryRule::class, Rules\DriftQLJoinsRule::class] as $rule) {
            \Clicalmani\Foundation\Providers\ValidationServiceProvider::addRule($rule);
        }

        // Register DriftQL console command handlers
        foreach ([
            Console\MakeConfig::class, 
            Console\MakeModel::class, 
            Console\CreateEntities::class, 
            Console\MakeContract::class
        ] as $command) {
            app()->addCommand($command);
        }

        // Initialize console application when running under CLI mode
        if ( isConsoleMode() ) {
            app()->console->make();
        }
    }

    /**
     * Boot and load the DriftQL configuration settings into the global environment.
     *
     * @return void
     */
    public function boot(): void
    {
        if ( is_file(config_path('/driftql.php')) ) {
            app()->config->set('driftql', require_once config_path('/driftql.php'));
        }
    }
}