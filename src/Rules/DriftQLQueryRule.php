<?php
namespace Tonka\DriftQL\Rules;

/**
 * Class DriftQLQueryRule
 *
 * Validates complex DriftQL query payloads, including pagination limits/offsets, 
 * policy authorization, ORDER BY, GROUP BY, WHERE, HAVING, JOIN, and FILTER constraints.
 *
 * @package Tonka\DriftQL\Rules
 * @author clicalmani
 */
class DriftQLQueryRule extends DriftQLRule
{
    /**
     * Rule argument identifier for validation mapping.
     * 
     * @var string
     */
    protected static string $argument = "dql_query";

    /**
     * Custom error message generated during validation failures.
     * 
     * @var string
     */
    private string $error_message = '';

    /**
     * Validate and normalize the incoming query payload structure.
     * 
     * @param mixed &$query JSON string or decoded query structure passed by reference.
     * @return bool True if the query structure and security rules pass, false otherwise.
     */
    public function validate(mixed &$query) : bool
    {
        $allowedOperators = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'];
        
        $query = json_decode($query, true);
        
        // Ensure the payload contains essential root structure keys
        if ( ! is_array($query) || ! isset($query['offset'], $query['orders'], $query['wheres']) ) {
            $this->error_message = 'Query must be a valid JSON array with keys: limit, offset, orders, wheres.';
            return false;
        }

        $limit   = $query['limit'] ?? config('driftql.limits.default_limit');
        $offset  = $query['offset'] ?? 0;
        $orders  = $query['orders'] ?? [];
        $groups  = $query['groups'] ?? [];
        $wheres  = $query['wheres'] ?? [];
        $havings = $query['havings'] ?? [];
        $joins   = $query['joins'] ?? [];
        $withs   = $query['withs'] ?? [];
        $filters = $query['filters'] ?? [];
        
        $query['limit']   = $limit;
        $query['offset']  = $offset;
        $query['orders']  = $orders;
        $query['wheres']  = $wheres;
        $query['withs']   = $withs;
        $query['filters'] = $filters;

        // Validate numeric integers for pagination
        if ( ! preg_match('/^\d+$/', $limit) || ! preg_match('/^\d+$/', $offset) ) {
            $this->error_message = "Limit and offset must be positive integers";
        }

        // Cap limit to the configured maximum threshold
        if ($limit > config('driftql.limits.max_limit')) {
            $query['limit'] = config('driftql.limits.max_limit');
        }

        // Perform authorization check via target policy contract if applicable
        if ($policy = $this->getPolicy()) {
            if ( is_subclass_of($policy, \Clicalmani\Foundation\Auth\Contract::class) && ! (new $policy)->authorize() ) {
                $this->error_message = "Unauthorized query";
                return false;
            }
        }

        // Validate ORDER BY clauses
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

        // Validate GROUP BY clauses
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

        // Validate WHERE clauses and enforce SQL injection prevention checks
        foreach ($wheres as $clause) {

            if ( ! isset($clause['column'], $clause['operator'], $clause['value'], $clause['boolean']) ) {
                $this->error_message = 'Invalid where clause configuration';
                return false;
            }

            if ($this->isStrictColumnCheckActive() && !$joins && !$this->columnExists($this->cleanKey($clause['column']))) {
                $this->error_message = sprintf('Where clause column "%s" does not exist in the database schema', $clause['column']);
                return false;
            }

            $column   = $clause['column'];
            $operator = strtoupper($clause['operator']);
            $value    = $clause['value'];
            $boolean  = strtolower($clause['boolean'] ?? 'and');

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

            // Prevent aggregate function execution in where clauses
            if ( preg_match('/\b(AVG|COUNT|MIN|MAX|SUM|GROUP_CONCAT|NOW|CURDATE|CURTIME|YEAR|MONTH|DAY|HOUR|IFNULL|COALESCE)\s*\(/i', $column) ) {
                $this->error_message = "Aggregate functions are not allowed in where clause";
                return false;
            }

            // Prevent raw subqueries and dynamic DDL/DML statements
            if ( preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|RENAME|TRUNCATE|EXEC|UNION|HAVING|JOIN)\b/i', $column) ) {
                $this->error_message = "Subqueries and SQL keywords are not allowed in where clause";
                return false;
            }

            // Reject dynamic function invocation within column expressions
            if ( preg_match('/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $column) ) {
                $this->error_message = "Functions are not allowed in where clause";
                return false;
            }
        }

        // Validate HAVING clauses
        foreach ($havings as $clause) {

            if ( ! isset($clause['column'], $clause['operator'], $clause['value'], $clause['boolean']) ) {
                $this->error_message = 'Invalid having clause configuration';
                return false;
            }

            $operator = strtoupper($clause['operator']);
            $value    = $clause['value'];
            $boolean  = strtolower($clause['boolean']);

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

        // Validate JOIN configurations and whitelisted relations
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
                $resource     = $join['resource'];
                $model        = trim("App\\Models\\$resource");

                if ( ! $this->isWhiteListed($model) ) {
                    $this->error_message = "The model '$model' is not allowed. Please add it to the whitelist in the DriftQL configuration.";
                    return false;
                }

                $join['resource'] = "\\$model";

                $foreign_key  = $join['fkey'] ?? null;
                $original_key = $join['okey'] ?? null;

                if ($this->isStrictColumnCheckActive() && $foreign_key && !$this->columnExists($this->cleanKey($foreign_key))) {
                    $this->error_message = "Foreign key '$foreign_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                    return false;
                }

                if ($this->isStrictColumnCheckActive() && $original_key && !$this->columnExists($this->cleanKey($original_key))) {
                    $this->error_message = "Original key '$original_key' does not exist in the model '" . $this->getRequestedModel() . "'.";
                    return false;
                }

                // Verify that at least one column references the main or joined table
                foreach ($wheres as $clause) {
                    if ($this->isStrictColumnCheckActive() && ($this->columnExists($this->cleanKey($clause['column'])) || $this->columnExists($this->cleanKey($clause['column']), $model))) {
                        $ok = true;
                    }
                }

                foreach ($orders as $order) {
                    if ($this->isStrictColumnCheckActive() && ($this->columnExists($this->cleanKey($order['column'])) || $this->columnExists($this->cleanKey($order['column']), $model))) {
                        $ok = true;
                    }
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

        // Validate simple filter attributes against schema
        if ($filters) {
            foreach ($filters as $column => $value) {
                if ($this->isStrictColumnCheckActive() && !$this->columnExists($column)) {
                    $this->error_message = sprintf('Filtered column "%s" does not exist in the database schema', $column);
                    return false;
                }
            }
        }

        if ( $this->error_message ) {
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