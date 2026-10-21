<?php
namespace Tonka\DriftQL;

use Clicalmani\Database\Factory\Entity;

final class Column
{
    /**
     * @param int $index
     * @param Entity $entity
     * @return ?string
     */
    public static function fromIndex(int $index, Entity $entity): ?string
    {
        foreach ($entity->getAttributes() as $key => $attribute) {
            if ($key === $index) return $attribute->name;
        }

        return null;
    }
}