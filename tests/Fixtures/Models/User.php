<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures\Models;

use Clicalmani\Database\Factory\Models\Elegant;
use Tonka\DriftQL\Tests\Fixtures\Entities\UserEntity;

final class User extends Elegant
{
    protected string $table = 'users';

    protected string $entity = UserEntity::class;

    protected string|array $primaryKey = 'id';
}
