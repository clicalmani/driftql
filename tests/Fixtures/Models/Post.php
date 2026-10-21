<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures\Models;

use Clicalmani\Database\Factory\Models\Elegant;
use Tonka\DriftQL\Tests\Fixtures\Entities\PostEntity;

final class Post extends Elegant
{
    protected string $table = 'posts';

    protected string $entity = PostEntity::class;

    protected string|array $primaryKey = 'id';
}
