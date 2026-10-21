<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests;

use Clicalmani\Core\Acme\Configure;
use Clicalmani\Core\Http\Request;
use Clicalmani\Core\Http\RequestInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionProperty;
use Tonka\DriftQL\Tests\Fixtures\FakeRequest;

/**
 * Isole la configuration statique et la requête courante entre chaque test.
 */
abstract class TestCase extends BaseTestCase
{
    private array $configSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = self::configureStorage();
        self::setCurrentRequest(null);
        \Tonka\DriftQL\Tests\Fixtures\Policies\RecordingRequestPolicy::reset();
    }

    protected function tearDown(): void
    {
        self::setCurrentRequest(null);
        self::setConfigureStorage($this->configSnapshot);

        parent::tearDown();
    }

    protected function setConfig(string $key, mixed $value): void
    {
        container()->get('config')->set($key, $value);
    }

    protected function useRequest(array $inputs = [], mixed $user = null): FakeRequest
    {
        $request = new FakeRequest($inputs, $user);
        self::setCurrentRequest($request);

        return $request;
    }

    private static function configureStorage(): array
    {
        return (new ReflectionProperty(Configure::class, 'storage'))->getValue();
    }

    private static function setConfigureStorage(array $storage): void
    {
        (new ReflectionProperty(Configure::class, 'storage'))->setValue(null, $storage);
    }

    private static function setCurrentRequest(?RequestInterface $request): void
    {
        (new ReflectionProperty(Request::class, 'current_request'))->setValue(null, $request);
    }
}
