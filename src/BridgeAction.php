<?php
namespace Tonka\DriftQL;

/**
 * Actions supported by the DriftQL bridge, identified on the client side by a
 * SHA-1 hash of their name (timing-safe comparison via hash_equals).
 */
enum BridgeAction: string
{
    case Store          = 'store';
    case Update         = 'update';
    case Delete         = 'delete';
    case VerifyPassword = 'verify_password';
    case Filters        = 'filters';
    case TotalRows      = 'get_total_with_filters';

    /**
     * Returns the associated Bridge action class name for execution.
     *
     * @return class-string<BridgeInterface>
     */
    public function bridgeClass(): string
    {
        return match($this) {
            self::Store, self::Update => WriteBridge::class,
            self::Delete               => DestroyBridge::class,
            self::VerifyPassword       => PasswordVerifyBridge::class,
            self::Filters, 
            self::TotalRows            => SelectBridge::class,
        };
    }

    /**
     * Compares the received hash to the SHA-1 hash of this action's string value in a timing-safe manner.
     *
     * @param string $hash The incoming SHA-1 hash to check against this action.
     * @return bool True if the hash matches, false otherwise.
     */
    public function matchesHash(string $hash): bool
    {
        return hash_equals($hash, sha1($this->value));
    }

    /**
     * Retrieves the action corresponding to a given SHA-1 hash, or null if no known
     * action matches (default route fallback: SelectBridge).
     *
     * @param string $hash The SHA-1 hash sent by the client.
     * @return self|null The matching enum case, or null if unhandled.
     */
    public static function fromHash(string $hash): ?self
    {
        foreach (self::cases() as $action) {
            if ($action->matchesHash($hash)) {
                return $action;
            }
        }

        return null;
    }
}