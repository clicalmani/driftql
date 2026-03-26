<?php
namespace Tonka\DriftQL\Rules;

class DriftQLModelRule extends DriftQLRule
{
    /**
     * Rule argument
     * 
     * @var string
     */
    protected static string $argument = "dql_model";

    private string $error_message = '';

    /**
     * Validate input
     * 
     * @param mixed &$value Input value
     * @return bool
     */
    public function validate(mixed &$value) : bool
    {
        if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value) ) return false;

        if ( ! $this->isWhiteListed() ) {
            $this->error_message = sprintf("The model '%s' is not allowed. Please add it to the whitelist in the DriftQL configuration.", $this->getRequestedModel());
            return false;
        }

        return true;
    }

    /**
     * Gets the custom error message.
     * 
     * @return string
     */
    public function message() : ?string
    {
        return $this->error_message;
    }
}
