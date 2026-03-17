<?php
namespace Tonka\DriftQL;

use Clicalmani\Foundation\Providers\ServiceProvider;

class DriftQLServiceProvider extends ServiceProvider
{
    private static $config;

    public function register(): void
    {
        parent::register();

        foreach ([Rules\DriftQLModelRule::class, Rules\DriftQLQueryRule::class] as $rule) {
            \Clicalmani\Foundation\Providers\ValidationServiceProvider::addRule($rule);
        }
    }

    public function boot(): void
    {
        static::$config = require_once config_path('/driftql.php');
    }

    public static function getConfig()
    {
        return static::$config;
    }
}