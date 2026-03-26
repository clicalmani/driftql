<?php
namespace Tonka\DriftQL\Rules;

class DriftQLJoinsRule extends DriftQLRule
{
    /**
     * Rule argument
     * 
     * @var string
     */
    protected static string $argument = "dql_joins";

    private string $error_message = '';

    /**
     * Validate input
     * 
     * @param mixed &$joins Input value
     * @return bool
     */
    public function validate(mixed &$joins) : bool
    {
        $joins = json_decode($joins, true);

        if ( ! is_array($joins) ) {
            $this->error_message = 'Joins must be a valid JSON array.';
            return false;
        }

        if ( empty($joins) ) return true;

        foreach ($joins as $index => $join) {
            if ( ! isset($join['resource'], $join['type']) ) {
                $this->error_message = 'Each join must have a resource and type.';
                return false;
            }

            if ( ! in_array($join['type'], ['inner', 'left', 'right', 'cross'])) {
                $this->error_message = "Join type '" . $join['type'] . "' is not valid. Allowed types are: inner, left, right, cross.";
                return false;
            } else {
                $join['type'] = strtolower($join['type']);
            }

            $resource = $join['resource'];
            $model = trim("App\\Models\\$resource");

            if ( ! $this->isWhiteListed($model) ) {
                $this->error_message = "The model '$model' is not allowed. Please add it to the whitelist in the DriftQL configuration.";
                return false;
            }

            $join['resource'] = "\\$model";

            $foreign_key = isset($join['fkey']) ? $this->cleanKey($join['fkey']): null;
            $original_key = isset($join['fkey']) ? $this->cleanKey($join['okey']): null;

            if ( NULL === $foreign_key ) {
                $join['fkey'] = null;
            } elseif ($this->isStrictColumnCheckActive() && !$this->columnExists($foreign_key)) {
                $this->error_message = "Foreign key '$foreign_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                return false;
            }

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
     * Gets the custom error message.
     * 
     * @return string
     */
    public function message() : ?string
    {
        return $this->error_message;
    }

    private function cleanKey(string $key): string
    {
        $arr = explode('.', $key);
        return end($arr);
    }
}
