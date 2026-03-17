<?php
namespace Tonka\DriftQL\Rules;

use Tonka\DriftQL\DriftQLServiceProvider;

class DriftQLModelRule extends \Clicalmani\Validation\Rule
{
    /**
     * Rule argument
     * 
     * @var string
     */
    protected static string $argument = "dql_model";

    /**
     * Validate input
     * 
     * @param mixed &$value Input value
     * @return bool
     */
    public function validate(mixed &$value) : bool
    {
        if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value) ) return false;

        $config = DriftQLServiceProvider::getConfig();
        $allowed_models = $config['whitelist']['allowed_models'];
        $model = trim("App\\Models\\$value");
        
        if ( ! in_array($model, $allowed_models) ) return false;

        return true;
    }

    /**
     * Gets the custom error message.
     * 
     * @return string
     */
    public function message() : ?string
    {
        return 'Invalid model name';
    }
}
