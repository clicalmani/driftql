<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests;

use Clicalmani\Core\Http\Request;
use Tonka\DriftQL\Exceptions\DriftQLException;
use Tonka\DriftQL\Tests\Fixtures\BridgeProbe;
use Tonka\DriftQL\Tests\Fixtures\Models\Post;
use Tonka\DriftQL\Tests\Fixtures\Models\User;
use Tonka\DriftQL\Tests\Fixtures\Policies\DenyPolicy;
use Tonka\DriftQL\Tests\Fixtures\Policies\RecordingRequestPolicy;

final class BridgePolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig('driftql.whitelist.allowed_models', ['users' => User::class]);
        $this->useRequest(['__dq_model' => 'users', 'name' => 'Alice']);
    }

    public function test_initializes_and_returns_a_request_policy(): void
    {
        $this->setConfig('driftql.policies', [User::class => ['store' => RecordingRequestPolicy::class]]);

        $policy = (new BridgeProbe)->policy('store');

        self::assertInstanceOf(RecordingRequestPolicy::class, $policy);
        self::assertSame(['make', 'authorize', 'prepareForValidation', 'signatures'], RecordingRequestPolicy::$calls);
        self::assertSame(['__dq_model' => 'users', 'name' => 'Alice'], $policy->all());
        self::assertSame($policy, Request::current());
    }

    public function test_uses_a_scalar_policy_for_any_action(): void
    {
        $this->setConfig('driftql.policies', [User::class => RecordingRequestPolicy::class]);

        self::assertInstanceOf(RecordingRequestPolicy::class, (new BridgeProbe)->policy('destroy'));
    }

    public function test_selects_the_policy_matching_the_action(): void
    {
        $this->setConfig('driftql.policies', [
            User::class => [
                'store'   => RecordingRequestPolicy::class,
                'destroy' => DenyPolicy::class,
            ],
        ]);

        self::assertInstanceOf(RecordingRequestPolicy::class, (new BridgeProbe)->policy('store'));
    }

    public function test_throws_when_the_model_has_no_policy(): void
    {
        $this->setConfig('driftql.policies', [Post::class => ['store' => RecordingRequestPolicy::class]]);

        $this->expectException(DriftQLException::class);
        $this->expectExceptionMessage('Model ' . User::class . ' does not have a policy for action store');

        (new BridgeProbe)->policy('store');
    }

    public function test_throws_when_the_action_has_no_policy(): void
    {
        $this->setConfig('driftql.policies', [User::class => ['destroy' => DenyPolicy::class]]);

        $this->expectException(DriftQLException::class);
        $this->expectExceptionMessage('Model ' . User::class . ' does not have a policy for action store');

        (new BridgeProbe)->policy('store');
    }

    public function test_throws_when_a_contract_policy_denies_access(): void
    {
        $this->setConfig('driftql.policies', [User::class => ['store' => DenyPolicy::class]]);

        $this->expectException(DriftQLException::class);
        $this->expectExceptionMessage('Unauthorized access to model ' . User::class);

        (new BridgeProbe)->policy('store');
    }

    public function test_throws_when_the_policy_is_not_a_supported_class(): void
    {
        $this->setConfig('driftql.policies', [User::class => ['store' => \stdClass::class]]);

        $this->expectException(DriftQLException::class);
        $this->expectExceptionMessage('must be a subclass of');

        (new BridgeProbe)->policy('store');
    }
}
