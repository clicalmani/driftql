<?php
namespace Tonka\DriftQL\Rules;

abstract class DriftQLRule extends \Clicalmani\Validation\Rule
{
    protected function columnExists(string $column, ?string $model = null): bool 
    {
        $model = $model ?: "\\" . $this->getRequestedModel();
        $parts = explode('.', $column);
        $colName = end($parts);

        $tableColumns = \Clicalmani\Database\Factory\Schema::getColumnListing((new $model)->getTable());
        
        return in_array($colName, $tableColumns);
    }

    protected function getRequestedModel(): string
    {
        return trim("App\\Models\\" . request()->input('__dq_model'));
    }

    protected function getCurrentUserRole(): string
    {
        return auth()->user()->role;
    }

    protected function isStrictColumnCheckActive(): bool
    {
        return !!config('driftql.security.strict_column_check');
    }

    protected function getPolicy(): string|array|null
    {
        return isset(config('driftql.policies.' . $this->getRequestedModel() . '.' . $this->getCurrentUserRole())['policies']) ?
                $this->isConfirmed()['policies'][$this->getRequestedModel()][$this->getCurrentUserRole()]: null;
    }

    protected function isWhiteListed(?string $resource = null): bool
    {
        return in_array($resource ?? $this->getRequestedModel(), config('driftql.whitelist.allowed_models'));
    }

    protected function cleanKey(string $key): string
    {
        $arr = explode('.', $key);
        return end($arr);
    }
}