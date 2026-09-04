<?php
namespace Tonka\DriftQL\Rules;

use Tonka\DriftQL\FindModel;

/**
 * Class DriftQLRule
 *
 * Abstract base validation rule providing core model resolution, schema verification,
 * security whitelisting, and authorization policy checks across all DriftQL rules.
 *
 * @package Tonka\DriftQL\Rules
 * @author clicalmani
 */
abstract class DriftQLRule extends \Clicalmani\Validation\Rule
{
    use FindModel;

    /**
     * Checks whether a specific column attribute exists within the target model's entity schema.
     *
     * @param string $column The column attribute name to verify.
     * @return bool True if the column exists in the schema, false otherwise.
     */
    protected function columnExists(string $column): bool 
    {
        /** @var ?\Clicalmani\Foundation\Acme\Model */
        $modelClass = $this->getRequestedModel();

        return (new $modelClass())->getEntity()->attributeExists($column);
    }

    /**
     * Retrieves the role assigned to the currently authenticated user.
     *
     * @return string The user's role identifier, or an empty string if unauthenticated.
     */
    protected function getCurrentUserRole(): string
    {
        return auth()?->user()?->role ?? '';
    }

    /**
     * Determines if strict column schema validation is enabled in configuration.
     *
     * @return bool True if strict column checking is enabled, false otherwise.
     */
    protected function isStrictColumnCheckActive(): bool
    {
        return (bool) config('driftql.security.strict_column_check');
    }

    /**
     * Retrieves authorization policy handlers for the requested model and current user role.
     *
     * @return string|array|null The matching policy configuration or null if not defined.
     */
    protected function getPolicy(): string|array|null
    {
        return isset(config('driftql.policies.' . $this->getRequestedModel() . '.' . $this->getCurrentUserRole())['policies']) 
            ? $this->isConfirmed()['policies'][$this->getRequestedModel()][$this->getCurrentUserRole()] 
            : null;
    }

    /**
     * Checks if a target model resource is permitted under the DriftQL allowed models whitelist.
     *
     * @param string|null $resource The model class or name to check. Defaults to the requested model if omitted.
     * @return bool True if the model is whitelisted, false otherwise.
     */
    protected function isWhiteListed(?string $resource = null): bool
    {
        return in_array($resource ?? $this->getRequestedModel(), config('driftql.whitelist.allowed_models'), true);
    }

    /**
     * Extracts the raw column key name by stripping table aliases or prefix qualifiers.
     *
     * @param string $key The key or qualified column identifier (e.g., "users.id").
     * @return string The clean column name (e.g., "id").
     */
    protected function cleanKey(string $key): string
    {
        $arr = explode('.', $key);
        return end($arr);
    }
}