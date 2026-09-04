<?php
namespace Tonka\DriftQL;

/**
 * Trait AppliesArrayPolicy
 *
 * Provides capabilities for resolving and generating parameterized database criteria 
 * based on role-based array policy definitions in configuration.
 *
 * @package Tonka\DriftQL
 * @author clicalmani
 */
trait AppliesArrayPolicy
{
    /**
     * Applies policy rules for a given model and user role to build SQL criteria and bindings.
     *
     * @param string $model The class name or alias of the target model.
     * @param string $role The role identifier of the current user.
     * @return array Returns an array with 'criteria' and 'bindings', or an empty array if no policy applies.
     */
    private function apply(string $model, string $role): array
    {
        /** @var array */
        $config = $this->getConfig();

        if (isset($config['policies'][$model][$role])) {
            $policy = $config['policies'][$model][$role];

            if ( is_array($policy) ) {

                // Verify required policy configuration keys
                if (!isset($policy['column'], $policy['operator'], $policy['value'])) {
                    return response()->error('Invalid policy configuration');
                }

                // Ensure specified policy column exists within the model schema
                if (!$this->columnExists($policy['column'])) {
                    return response()->error('Policy column does not exist in the database schema');
                }

                // Resolve dynamic placeholders like authenticated user ID
                $value = ($policy['value'] === 'current_user_id') 
                    ? auth()->id() 
                    : $policy['value'];

                return [
                    'criteria' => $policy['column'] . $policy['operator'] . ' ?',
                    'bindings' => [$value]
                ];
            }
        }

        return [];
    }
}