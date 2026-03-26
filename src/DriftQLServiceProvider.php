<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Providers\ServiceProvider;

class DriftQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        foreach ([Rules\DriftQLModelRule::class, Rules\DriftQLQueryRule::class, Rules\DriftQLJoinsRule::class] as $rule) {
            \Clicalmani\Foundation\Providers\ValidationServiceProvider::addRule($rule);
        }

        foreach ([
            Console\MakeConfig::class, 
            Console\MakeModel::class, 
            Console\CreateEntities::class, 
            Console\MakeContract::class
        ] as $command) {
            app()->addCommand($command);
        }

        if ( isConsoleMode() ) {
            app()->console->make();
        }
    }

    public function boot(): void
    {
        if ( is_file(config_path('/driftql.php')) ) {
            app()->config->set('driftql', require_once config_path('/driftql.php'));
        }
    }
}