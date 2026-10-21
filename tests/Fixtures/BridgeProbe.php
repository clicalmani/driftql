<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures;

use Clicalmani\Core\Http\RequestInterface;
use Clicalmani\Database\Factory\Models\ModelInterface;
use Tonka\DriftQL\Bridge;

/**
 * Expose les points d'entrée protégés de Bridge pour les tests.
 */
final class BridgeProbe extends Bridge
{
    public function requestedModel(): string
    {
        return $this->getRequestedModel();
    }

    public function policy(?string $action = null): ?RequestInterface
    {
        return $this->getPolicy($action);
    }

    public function model(): ModelInterface
    {
        return $this->getModel();
    }
}
