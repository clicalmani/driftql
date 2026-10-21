<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures\Policies;

use Clicalmani\Core\Http\Request;

/**
 * Politique de type Request : enregistre l'ordre des appels de getPolicy().
 */
final class RecordingRequestPolicy extends Request
{
    /** @var string[] */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function make(array $params = []): void
    {
        self::$calls[] = 'make';
        parent::make($params);
    }

    public function authorize(): bool
    {
        self::$calls[] = 'authorize';
        return true;
    }

    public function prepareForValidation(): void
    {
        self::$calls[] = 'prepareForValidation';
    }

    public function signatures(): void
    {
        self::$calls[] = 'signatures';
    }
}
