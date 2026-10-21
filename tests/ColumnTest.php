<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests;

use Tonka\DriftQL\Column;
use Tonka\DriftQL\Tests\Fixtures\Models\Post;
use Tonka\DriftQL\Tests\Fixtures\Models\User;

final class ColumnTest extends TestCase
{
    public function test_maps_entity_attribute_indexes_to_column_names(): void
    {
        $entity = (new User)->getEntity();

        self::assertSame('id', Column::fromIndex(0, $entity));
        self::assertSame('name', Column::fromIndex(1, $entity));
        self::assertSame('email', Column::fromIndex(2, $entity));
    }

    public function test_mapping_follows_the_entity_property_declaration_order(): void
    {
        $entity = (new Post)->getEntity();

        self::assertSame('id', Column::fromIndex(0, $entity));
        self::assertSame('user_id', Column::fromIndex(1, $entity));
        self::assertSame('title', Column::fromIndex(2, $entity));
    }

    public function test_returns_null_for_an_unknown_index(): void
    {
        self::assertNull(Column::fromIndex(99, (new User)->getEntity()));
        self::assertNull(Column::fromIndex(-1, (new User)->getEntity()));
    }
}
