<?php
namespace Tonka\DriftQL\Rules;

use Clicalmani\Database\Factory\Schema;
use Clicalmani\Foundation\Support\Facades\DB;
use Tonka\DriftQL\DriftQLServiceProvider;

class DriftQLQueryRule extends \Clicalmani\Validation\Rule
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
        $config = DriftQLServiceProvider::getConfig();
        $allowedOperators = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'];
        
        $query = json_decode($query, true);
        $limit = $query['limit'];
        $offset = $query['offset'];
        $orders = $query['orders'];
        $wheres = $query['wheres'];

        if ( ! preg_match('/^\d+$/', $limit) || ! preg_match('/^\d+$/', $offset) ) {
            $this->error_message = "Limit and offset must be positive integers";
        }

        if (!$limit) {
            $query['limit'] = $config['limits']['default_limit'];
        } elseif ($limit > $config['limits']['max_limit']) {
            $query['limit'] = $config['limits']['max_limit'];
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

            if ( $config['security']['strict_column_check'] && !$this->isColumnAllowed($column) ) {
                $this->error_message = "Colomn $column not allowed";
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

    private function isColumnAllowed(string $column): bool 
    {
        $model = "\\App\\Models\\" . request()['model'];
        $parts = explode('.', $column);
        $colName = end($parts);

        $tableColumns = Schema::getColumnListing((new $model)->getTable());
        
        return in_array($colName, $tableColumns);
    }
}
