<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests;

use Clicalmani\Core\Http\ResponseInterface;
use Clicalmani\Core\Support\Facades\DB;
use Clicalmani\Database\Factory\Models\Elegant;
use ReflectionMethod;
use ReflectionProperty;
use Tonka\DriftQL\SelectBridge;
use Tonka\DriftQL\Tests\Fixtures\Models\Post;
use Tonka\DriftQL\Tests\Fixtures\Models\User;
use Tonka\DriftQL\Tests\Fixtures\RecordingBuilder;

final class SelectBridgeTest extends TestCase
{
    private SelectBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bridge = new SelectBridge;
        $this->setConfig('driftql.whitelist.allowed_models', [
            'users' => User::class,
            'posts' => Post::class,
        ]);

        // HAVING est construit inline : l'échappement des valeurs passe par PDO.
        $this->setConfig('database', [
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
        ]);
        DB::setPdo(new \PDO('sqlite::memory:'));
    }

    public function test_builds_a_condition_from_an_entity_index(): void
    {
        $this->useModel(new User);

        [$sql, $bindings] = $this->call('buildConditionSegment', [
            'column'   => 1,
            'operator' => '=',
            'value'    => 'Alice',
            'boolean'  => 'and',
        ]);

        self::assertSame('name = ?', $sql);
        self::assertSame(['Alice'], $bindings);
    }

    public function test_keeps_a_literal_column_name(): void
    {
        $this->useModel(new User);

        [$sql, $bindings] = $this->call('buildConditionSegment', [
            'column'   => 'email',
            'operator' => 'LIKE',
            'value'    => '%@example.com',
            'boolean'  => 'and',
        ]);

        self::assertSame('email LIKE ?', $sql);
        self::assertSame(['%@example.com'], $bindings);
    }

    public function test_expands_the_in_operator(): void
    {
        $this->useModel(new User);

        [$sql, $bindings] = $this->call('buildConditionSegment', [
            'column'   => 0,
            'operator' => 'IN',
            'value'    => [1, 2, 3],
            'boolean'  => 'and',
        ]);

        self::assertSame('id IN (?, ?, ?)', $sql);
        self::assertSame([1, 2, 3], $bindings);
    }

    public function test_expands_the_between_operator(): void
    {
        $this->useModel(new User);

        [$sql, $bindings] = $this->call('buildConditionSegment', [
            'column'   => 0,
            'operator' => 'BETWEEN',
            'value'    => [1, 10],
            'boolean'  => 'and',
        ]);

        self::assertSame('id BETWEEN ? AND ?', $sql);
        self::assertSame([1, 10], $bindings);
    }

    public function test_builds_having_clauses_with_entity_indexes(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 1, 'operator' => '=', 'value' => 'Alice', 'boolean' => 'and'],
        ]);

        self::assertSame("1 and name = 'Alice'", $having);
    }

    public function test_builds_having_clauses_with_literal_column_names(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 'email', 'operator' => 'LIKE', 'value' => '%@example.com', 'boolean' => 'or'],
        ]);

        self::assertSame("1 or email LIKE '%@example.com'", $having);
    }

    public function test_quotes_having_values_to_prevent_sql_injection(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 1, 'operator' => '=', 'value' => "O'Reilly", 'boolean' => 'and'],
        ]);

        self::assertSame("1 and name = 'O''Reilly'", $having);
    }

    public function test_inlines_numeric_and_boolean_having_values(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 0, 'operator' => '>', 'value' => 5, 'boolean' => 'and'],
            ['column' => 1, 'operator' => '=', 'value' => true, 'boolean' => 'and'],
        ]);

        self::assertSame('1 and id > 5 and name = 1', $having);
    }

    public function test_expands_in_and_not_in_having_operators(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 0, 'operator' => 'IN', 'value' => [1, 2], 'boolean' => 'and'],
            ['column' => 1, 'operator' => 'NOT IN', 'value' => ['Alice', 'Bob'], 'boolean' => 'and'],
        ]);

        self::assertSame("1 and id IN (1, 2) and name NOT IN ('Alice', 'Bob')", $having);
    }

    public function test_expands_the_between_having_operator(): void
    {
        $this->useModel(new User);

        $having = $this->call('buildHavingClause', [
            ['column' => 0, 'operator' => 'BETWEEN', 'value' => [1, 10], 'boolean' => 'and'],
        ]);

        self::assertSame('1 and id BETWEEN 1 AND 10', $having);
    }

    public function test_applies_order_by_with_indexes_and_directions(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyOrderBy', $builder, [
            ['column' => 1, 'direction' => 'DESC'],
            ['column' => 0, 'direction' => 'ASC'],
        ]);

        self::assertSame([[['name', 'id'], ['DESC', 'ASC']]], $builder->orderByCalls);
    }

    public function test_applies_order_by_with_literal_column_names(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyOrderBy', $builder, [
            ['column' => 'email', 'direction' => 'ASC'],
        ]);

        self::assertSame([[['email'], ['ASC']]], $builder->orderByCalls);
    }

    public function test_applies_group_by_with_entity_indexes(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyGroupBy', $builder, [
            ['column' => 1, 'direction' => 'ASC'],
        ]);

        self::assertSame(['name ASC'], $builder->groupByCalls);
    }

    public function test_applies_group_by_with_literal_column_names(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyGroupBy', $builder, [
            ['column' => 'email', 'direction' => 'ASC'],
        ]);

        self::assertSame(['email ASC'], $builder->groupByCalls);
    }

    public function test_applies_joins_with_resolved_tables_and_keys(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyJoins', $builder, [
            [
                'resource' => 'posts',
                'type'     => 'left',
                'fkey'     => 'posts.1',
                'okey'     => 'users.0',
            ],
        ]);

        self::assertSame([['left', 'posts', 'user_id', 'id']], $builder->joins);
    }

    public function test_applies_joins_declared_with_literal_column_names(): void
    {
        $this->useModel(new User);
        $builder = new RecordingBuilder;

        $this->call('applyJoins', $builder, [
            [
                'resource' => 'posts',
                'type'     => 'inner',
                'fkey'     => 'posts.user_id',
                'okey'     => 'users.id',
            ],
        ]);

        self::assertSame([['inner', 'posts', 'user_id', 'id']], $builder->joins);
    }

    public function test_rejects_a_join_on_a_model_that_is_not_whitelisted(): void
    {
        $this->useModel(new User);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Model for key 'comments' is not whitelisted");

        $this->call('applyJoins', new RecordingBuilder, [
            ['resource' => 'comments', 'type' => 'inner', 'fkey' => 'users.0', 'okey' => 'comments.0'],
        ]);
    }

    public function test_rejects_a_malformed_foreign_key(): void
    {
        $this->useModel(new User);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid foreign key format: user_id');

        $this->call('applyJoins', new RecordingBuilder, [
            ['resource' => 'posts', 'type' => 'inner', 'fkey' => 'user_id', 'okey' => 'users.0'],
        ]);
    }

    public function test_resolves_a_relation_key_declared_with_a_literal_column_name(): void
    {
        self::assertSame('user_id', $this->call('resolveRelationKey', 'posts.user_id'));
    }

    public function test_rejects_a_relation_key_with_an_unknown_column_index(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist in the model users');

        $this->call('resolveRelationKey', 'users.99');
    }

    public function test_rejects_a_relation_key_without_a_model_prefix(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid relation key format: users');

        $this->call('resolveRelationKey', 'users');
    }

    public function test_by_id_queries_drop_orders_and_limit_to_one(): void
    {
        $query = $this->call('applyByIdConstraints', [
            'orders' => [['column' => 0, 'direction' => 'ASC']],
            'limit'  => 50,
        ]);

        self::assertSame([], $query['orders']);
        self::assertSame(1, $query['limit']);
    }

    public function test_by_id_base_where_targets_the_primary_key(): void
    {
        $request = $this->useRequest([
            '__dq_model'   => 'users',
            '__dq_by_id'   => true,
            '__dq_id'      => '42',
        ]);

        [$where, $bindings] = $this->call('buildBaseWhere', $request, new User);

        self::assertSame('id = ?', $where);
        self::assertSame(['42'], $bindings);
    }

    public function test_an_invalid_policy_configuration_returns_an_error_response(): void
    {
        $this->useRequest(['__dq_model' => 'users'], (object) ['role' => 'admin']);
        $this->setConfig('driftql.policies', [
            User::class => ['admin' => ['column' => 'name']],
        ]);

        $result = $this->call('applyPolicy', new User, true, []);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    private function useModel(Elegant $model): void
    {
        (new ReflectionProperty(SelectBridge::class, 'modelInstance'))->setValue($this->bridge, $model);
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(SelectBridge::class, $method))->invoke($this->bridge, ...$arguments);
    }
}
