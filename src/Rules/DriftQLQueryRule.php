<?php
namespace Tonka\DriftQL\Rules;

class DriftQLQueryRule extends DriftQLRule
{
    /**
     * Rule argument
     * 
     * @var string
     */
    protected static string $argument = "dql_query";

    /**
     * Custom error message
     * 
     * @var string
     */
    private string $error_message = '';

    /**
     * Validate input
     * 
     * @param mixed &$value Input value
     * @return bool
     */
    public function validate(mixed &$query) : bool
    {
        $allowedOperators = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'];
        
        $query = json_decode($query, true);
        
        if ( ! is_array($query) || ! isset($query['offset'], $query['orders'], $query['wheres']) ) {
            $this->error_message = 'Query must be a valid JSON array with keys: limit, offset, orders, wheres.';
            return false;
        }

        $limit = $query['limit'] ?? config('driftql.limits.default_limit');
        $offset = $query['offset'] ?? 0;
        $orders = $query['orders'] ?? [];
        $wheres = $query['wheres'] ?? [];

        $query['limit'] = $limit;
        $query['offset'] = $offset;
        $query['orders'] = $orders;
        $query['wheres'] = $wheres;

        if ( ! preg_match('/^\d+$/', $limit) || ! preg_match('/^\d+$/', $offset) ) {
            $this->error_message = "Limit and offset must be positive integers";
        }

        if ($limit > config('driftql.limits.max_limit')) {
            $query['limit'] = config('driftql.limits.max_limit');
        }

        if ($policy = $this->getPolicy()) {
            if ( is_subclass_of($policy, \Tonka\DriftQL\Security\Contract::class) && ! (new $policy)->authorize() ) {
                $this->error_message = "Unauthorized query";
            }
        }

        foreach ($orders as $order) {
            if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $order['column']) || ! preg_match('/^(ASC|DESC)$/i', $order['direction'])) {
                $this->error_message = "Invalid order clause";
            }
        }

        foreach ($wheres as $clause) {
            $column = $clause['column'];
            $operator = strtoupper($clause['operator']);
            $value = $clause['value'];
            $boolean = $clause['boolean'] ?? 'and';

            if ( !in_array($operator, $allowedOperators) ) {
                $this->error_message = "Operator $operator not allowed";
            }

            if ( $this->isStrictColumnCheckActive() && !$this->columnExists($column) ) {
                $this->error_message = "Colomn $column does not exists";
            }

            if ( in_array($operator, ['IN', 'NOT IN']) && !is_array($value) ) {
                $this->error_message = "The $operator requires an array of values";
            }

            if ( !in_array($boolean, ['and', 'or']) ) {
                $this->error_message = "Boolean operator must be 'and' or 'or'";
            }
        }

        if ( $this->error_message ) return false;

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
