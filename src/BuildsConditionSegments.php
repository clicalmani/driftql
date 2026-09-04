<?php
namespace Tonka\DriftQL;

/**
 * Trait BuildsConditionSegments
 *
 * Constructs a WHERE or HAVING clause SQL segment along with its parameterized bindings
 * based on column, operator, and value, with native support for IN and BETWEEN operators.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
trait BuildsConditionSegments
{
    /**
     * Builds a single SQL condition segment and its associated parameter bindings.
     *
     * @param string $column Column name to apply the condition on.
     * @param string $operator SQL operator (e.g., '=', 'LIKE', 'IN', 'BETWEEN').
     * @param mixed $value The value or array of values for parameter binding.
     * @return array{0: string, 1: array} Tuple containing [string $sql, array $bindings].
     */
    private function buildConditionSegment(string $column, string $operator, mixed $value) : array
    {
        $sql = $column . ' ' . $operator . ' ?';
        $normalizedOperator = strtolower($operator);

        if ($normalizedOperator === 'in' && is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            return [str_replace('?', "($placeholders)", $sql), $value];
        }

        if ($normalizedOperator === 'between' && is_array($value) && count($value) === 2) {
            return [str_replace('?', '? AND ?', $sql), $value];
        }

        return [$sql, [$value]];
    }
}