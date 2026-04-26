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
        $groups = $query['groups'] ?? [];
        $wheres = $query['wheres'] ?? [];
        $havings = $query['havings'] ?? [];
        $joins = $query['joins'] ?? [];

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
            if ( is_subclass_of($policy, \Clicalmani\Foundation\Auth\Contract::class) && ! (new $policy)->authorize() ) {
                $this->error_message = "Unauthorized query";
                return false;
            }
        }

        foreach ($orders as $order) {

            if ( ! isset($order['column'], $order['direction']) ) {
                $this->error_message = 'Invalid order clause configuration';
                return false;
            }

            if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $order['column']) || ! preg_match('/^(ASC|DESC)$/i', $order['direction'])) {
                $this->error_message = "Invalid order clause";
                return false;
            }

            if ($this->isStrictColumnCheckActive() && !$joins && !$this->columnExists($this->cleanKey($order['column']))) {
                $this->error_message = sprintf('Order clause column "%s" does not exist in the database schema', $order['column']);
                return false;
            }
        }

        foreach ($groups as $group) {

            if ( ! isset($group['column'], $group['direction']) ) {
                $this->error_message = 'Invalid order clause configuration';
                return false;
            }

            if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $group['column']) || ! preg_match('/^(ASC|DESC)$/i', $group['direction'])) {
                $this->error_message = "Invalid order clause";
                return false;
            }
        }

        foreach ($wheres as $clause) {

            if ( ! isset($clause['column'], $clause['operator'], $clause['value'], $clause['operator'], $clause['boolean']) ) {
                $this->error_message = 'Invalid where clause configuration';
                return false;
            }

            if ($this->isStrictColumnCheckActive() && !$joins && !$this->columnExists($this->cleanKey($clause['column']))) {
                $this->error_message = sprintf('Where clause column "%s" does not exist in the database schema', $clause['column']);
                return false;
            }

            $column = $clause['column'];
            $operator = strtoupper($clause['operator']);
            $value = $clause['value'];
            $boolean = @$clause['boolean'];

            if ( !in_array($operator, $allowedOperators) ) {
                $this->error_message = "Operator $operator not allowed";
                return false;
            }

            if ( in_array($operator, ['IN', 'NOT IN']) && !is_array($value) ) {
                $this->error_message = "The $operator requires an array of values";
                return false;
            }

            if ( !in_array($boolean, ['and', 'or']) ) {
                $this->error_message = "Boolean operator must be 'and' or 'or'";
                return false;
            }

            // Avoid Aggragate usage in the where clause
            if ( preg_match('/\b(AVG|COUNT|MIN|MAX|SUM|GROUP_CONCAT|NOW|CURDATE|CURTIME|YEAR|MONTH|DAY|HOUR|IFNULL|COALESCE)\s*\(/i', $column) ) {
                $this->error_message = "Aggregate functions are not allowed in where clause";
                return false;
            }

            // Avoid subqueries
            if ( preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|RENAME|TRUNCATE|EXEC|UNION|HAVING|JOIN)\b/i', $column) ) {
                $this->error_message = "Subqueries and SQL keywords are not allowed in where clause";
                return false;
            }

            // Avoid usage of any function in where clause
            if ( preg_match('/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $column) ) {
                $this->error_message = "Functions are not allowed in where clause";
                return false;
            }
        }

        foreach ($havings as $clause) {

            if ( ! isset($clause['column'], $clause['operator'], $clause['value'], $clause['operator'], $clause['boolean']) ) {
                $this->error_message = 'Invalid having clause configuration';
                return false;
            }

            $operator = strtoupper($clause['operator']);
            $value = $clause['value'];
            $boolean = $clause['boolean'];

            if ( !in_array($operator, $allowedOperators) ) {
                $this->error_message = "Operator $operator not allowed";
                return false;
            }

            if ( in_array($operator, ['IN', 'NOT IN']) && !is_array($value) ) {
                $this->error_message = "The $operator operator requires an array of values";
                return false;
            }

            if ( !in_array($boolean, ['and', 'or']) ) {
                $this->error_message = "Boolean operator must be 'and' or 'or'";
                return false;
            }
        }

        if ($joins) {

            $ok = false;

            foreach ($joins as $index => $join) {

                if ( ! isset($join['resource'], $join['type']) ) {
                    $this->error_message = 'Each join must have a resource and type.';
                    return false;
                }

                if ( ! in_array($join['type'], ['inner', 'left', 'right', 'cross'])) {
                    $this->error_message = "Join type '" . $join['type'] . "' is not valid. Allowed types are: inner, left, right, cross.";
                    return false;
                }

                $join['type'] = strtolower($join['type']);
                $resource = $join['resource'];
                $model = trim("App\\Models\\$resource");

                if ( ! $this->isWhiteListed($model) ) {
                    $this->error_message = "The model '$model' is not allowed. Please add it to the whitelist in the DriftQL configuration.";
                    return false;
                }

                $join['resource'] = "\\$model";

                $foreign_key = @$join['fkey'] ?? null;
                $original_key = @$join['okey'] ?? null;

                if ($this->isStrictColumnCheckActive() && $foreign_key && !$this->columnExists($this->cleanKey($foreign_key))) {
                    $this->error_message = "Foreign key '$foreign_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                    return false;
                }

                if ($this->isStrictColumnCheckActive() && $original_key && !$this->columnExists($this->cleanKey($original_key))) {
                    $this->error_message = "Original key '$original_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                    return false;
                }

                foreach ($wheres as $clause) {
                    if ($this->isStrictColumnCheckActive() && ($this->columnExists($this->cleanKey($clause['column'])) || $this->columnExists($this->cleanKey($clause['column']), $model))) $ok = true;
                }

                foreach ($orders as $order) {
                    if ($this->isStrictColumnCheckActive() && ($this->columnExists($this->cleanKey($order['column'])) || $this->columnExists($this->cleanKey($order['column']), $model))) $ok = true;
                }

                $join['fkey'] = $foreign_key;
                $join['okey'] = $original_key;
                $joins[$index] = $join;
            }

            if ( !$ok ) {
                $this->error_message = "At least one where/order clause must reference a column from the joined table when strict column check is active.";
                return false;
            }

            $query['joins'] = $joins;
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
