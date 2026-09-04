<?php
namespace Tonka\DriftQL\Rules;

/**
 * Class DriftQLModelRule
 *
 * Validates the syntax of requested model names and enforces security 
 * restrictions using the configured DriftQL whitelist.
 *
 * @package Tonka\DriftQL\Rules
 * @author clicalmani
 */
class DriftQLModelRule extends DriftQLRule
{
    /**
     * Rule argument identifier for validation mapping.
     * 
     * @var string
     */
    protected static string $argument = "dql_model";

    /**
     * Holds the dynamically generated validation error message.
     *
     * @var string
     */
    private string $error_message = '';

    /**
     * Validate model name format and check whitelist permissions.
     * 
     * @param mixed &$value Model name input value passed by reference.
     * @return bool True if valid and whitelisted, false otherwise.
     */
    public function validate(mixed &$value) : bool
    {
        // Enforce valid PHP class identifier naming rules
        if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value) ) {
            return false;
        }

        // Verify that the requested model is permitted by the DriftQL whitelist
        if ( ! $this->isWhiteListed() ) {
            $this->error_message = sprintf(
                "The model '%s' is not allowed. Please add it to the whitelist in the DriftQL configuration.", 
                $this->getRequestedModel()
            );
            return false;
        }

        return true;
    }

    /**
     * Retrieve the validation failure error message.
     * 
     * @return string|null
     */
    public function message() : ?string
    {
        return $this->error_message;
    }
}