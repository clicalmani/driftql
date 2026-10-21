<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures;

/**
 * Query builder factice qui enregistre les appels de SelectBridge.
 */
final class RecordingBuilder
{
    public array $joins = [];

    public array $orderByCalls = [];

    public array $groupByCalls = [];

    public array $withCalls = [];

    public function leftJoin(...$arguments): void
    {
        $this->joins[] = ['left', ...$arguments];
    }

    public function innerJoin(...$arguments): void
    {
        $this->joins[] = ['inner', ...$arguments];
    }

    public function rightJoin(...$arguments): void
    {
        $this->joins[] = ['right', ...$arguments];
    }

    public function crossJoin(...$arguments): void
    {
        $this->joins[] = ['cross', ...$arguments];
    }

    public function orderBy(mixed $columns, mixed $directions = null): void
    {
        $this->orderByCalls[] = [$columns, $directions];
    }

    public function groupBy(mixed $columns): void
    {
        $this->groupByCalls[] = $columns;
    }

    public function with(mixed $relations): void
    {
        $this->withCalls[] = $relations;
    }
}
