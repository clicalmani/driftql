<?php
namespace Tonka\DriftQL\Rules;

/**
 * Class DriftQLJoinsRule
 *
 * Custom validation rule for decoding, validating, and normalizing DriftQL 
 * table join specifications and foreign key security constraints.
 *
 * @package Tonka\DriftQL\Rules
 * @author clicalmani
 */
class DriftQLJoinsRule extends DriftQLRule
{
    /**
     * Rule argument identifier for validation mapping.
     * 
     * @var string
     */
    protected static string $argument = "dql_joins";

    /**
     * Holds the dynamically generated validation error message.
     *
     * @var string
     */
    private string $error_message = '';

    /**
     * Validate and decode the incoming join specifications.
     * 
     * @param mixed &$joins Base64-encoded JSON payload of join configurations (passed by reference).
     * @return bool True if all join constraints pass, false otherwise.
     */
    public function validate(mixed &$joins) : bool
    {
        // Decode base64 string payload and parse outer JSON array
        $joins = base64_decode($joins);
        $joins = json_decode($joins, true);
        
        // Return early if no join payload is provided or if parsing fails
        if ( !$joins ) {
            $joins = [];
            return true;
        }
        
        foreach ($joins as $index => $join) {
            // Decode individually encoded join structures
            $join = json_decode($join, true);
            
            if ( !$join) {
                unset($joins[$index]);
                continue;
            }

            // Verify required attributes for a valid join construct
            if ( ! isset($join['resource'], $join['type']) ) {
                $this->error_message = 'Each join must have a resource and type.';
                return false;
            }

            // Validate join type against supported SQL join types
            if ( ! in_array($join['type'], ['inner', 'left', 'right', 'cross'])) {
                $this->error_message = "Join type '" . $join['type'] . "' is not valid. Allowed types are: inner, left, right, cross.";
                return false;
            } else {
                $join['type'] = strtolower($join['type']);
            }

            $resource = $join['resource'];
            $model = trim("App\\Models\\$resource");

            // Enforce model security whitelist checks
            if ( ! $this->isWhiteListed($model) ) {
                $this->error_message = "The model '$model' is not allowed. Please add it to the whitelist in the DriftQL configuration.";
                return false;
            }

            $join['resource'] = "\\$model";

            // Extract and clean key names for checking
            $foreign_key = isset($join['fkey']) ? $this->cleanKey($join['fkey']) : null;
            $original_key = isset($join['okey']) ? $this->cleanKey($join['okey']) : null; // Fixed variable bug from input source

            // Verify foreign key column existence if strict column checking is enabled
            if ( NULL === $foreign_key ) {
                $join['fkey'] = null;
            } elseif ($this->isStrictColumnCheckActive() && !$this->columnExists($foreign_key)) {
                $this->error_message = "Foreign key '$foreign_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                return false;
            }

            // Verify original primary/local key column existence if strict column checking is enabled
            if ( NULL === $original_key ) {
                $join['okey'] = null;
            } elseif ($this->isStrictColumnCheckActive() && !$this->columnExists($original_key)) {
                $this->error_message = "Original key '$original_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                return false;
            }

            $joins[$index] = $join;
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