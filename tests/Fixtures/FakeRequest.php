<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures;

use Clicalmani\Core\Http\Request;

/**
 * Requête HTTP en mémoire : évite de dépendre des superglobales PHP.
 */
final class FakeRequest extends Request
{
    public function __construct(
        private array $inputs = [],
        private mixed $fakeUser = null
    ) {
        parent::__construct();
    }

    public function all(): array
    {
        return $this->inputs;
    }

    public function input(?string $name = null, mixed $default = null): mixed
    {
        if (null === $name) return $this->inputs;
        return $this->inputs[$name] ?? $default;
    }

    public function __get($name)
    {
        return $this->inputs[$name] ?? null;
    }

    public function user(): mixed
    {
        return $this->fakeUser;
    }
}
