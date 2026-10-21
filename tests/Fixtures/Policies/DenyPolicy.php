<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures\Policies;

use Clicalmani\Core\Auth\Contract;

final class DenyPolicy extends Contract
{
    public function authorize(): bool
    {
        return false;
    }
}
