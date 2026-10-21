<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests;

use Tonka\DriftQL\Tests\Fixtures\BridgeProbe;
use Tonka\DriftQL\Tests\Fixtures\Models\Post;
use Tonka\DriftQL\Tests\Fixtures\Models\User;

final class FindModelTest extends TestCase
{
    public function test_resolves_a_whitelisted_model_key(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => 'users']);

        self::assertSame(User::class, (new BridgeProbe)->requestedModel());
    }

    public function test_caches_the_resolved_class_for_the_request(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => 'users']);

        $probe = new BridgeProbe;
        self::assertSame(User::class, $probe->requestedModel());

        $this->setConfig('driftql.whitelist.allowed_models', ['users' => Post::class]);

        self::assertSame(User::class, $probe->requestedModel());
    }

    public function test_rejects_a_model_key_that_is_not_whitelisted(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => 'posts']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Model index 'posts' is not allowed or not configured in the whitelist.");

        (new BridgeProbe)->requestedModel();
    }

    public function test_rejects_a_class_name_instead_of_a_whitelist_key(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => User::class]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not allowed or not configured in the whitelist');

        (new BridgeProbe)->requestedModel();
    }

    public function test_rejects_a_missing_model_input(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not allowed or not configured in the whitelist');

        (new BridgeProbe)->requestedModel();
    }

    public function test_get_model_returns_the_whitelisted_model_instance(): void
    {
        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => 'users']);

        self::assertInstanceOf(User::class, (new BridgeProbe)->model());
    }
}
