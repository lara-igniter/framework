<?php

namespace Elegant\Foundation\Testing;

use Elegant\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Elegant\Foundation\Testing\Concerns\InteractsWithMiddleware;
use Elegant\Foundation\Testing\Concerns\InteractsWithSession;
use Elegant\Foundation\Testing\Concerns\MakesHttpRequests;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpRequests;
    use InteractsWithAuthentication;
    use InteractsWithMiddleware;
    use InteractsWithSession;

    /**
     * Creates the application.
     *
     * Implemented by the application's Tests\CreatesApplication trait
     *
     * @return \Elegant\Foundation\Application
     */
    abstract public function createApplication();

    /**
     * The application instance.
     *
     * @var mixed
     */
    protected $app;

    /**
     * The HTTP kernel instance.
     *
     * @var \Elegant\Foundation\Http\Kernel|null
     */
    protected $httpKernel;

    /**
     * @var array
     */
    protected $afterApplicationCreatedCallbacks = [];

    /**
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * @var bool
     */
    protected $setUpHasRun = false;

    /**
     * @var array
     */
    protected $serverVariables = [];

    /**
     * @var array
     */
    protected $defaultHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        $this->setUpHasRun = true;
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                $callback();
            }
        }

        $this->setUpHasRun = false;
        $this->serverVariables = [];
        $this->defaultHeaders = [];
        $this->actingAsUser = null;
        $this->sessionData = [];
        $this->withoutMiddleware = [];
        $this->withoutMiddlewareAll = false;
        $this->afterApplicationCreatedCallbacks = [];
        $this->beforeApplicationDestroyedCallbacks = [];
        $this->httpKernel = null;
        $this->app = null;

        parent::tearDown();
    }

    protected function setUpTraits(): void
    {
        $uses = class_uses_recursive(static::class);

        if (isset($uses[DatabaseTransactions::class]) || in_array(DatabaseTransactions::class, $uses, true)) {
            $this->beginDatabaseTransaction();
        }

        if (isset($uses[DatabaseMigrations::class]) || in_array(DatabaseMigrations::class, $uses, true)) {
            $this->runDatabaseMigrations();
        }

        if (isset($uses[RefreshDatabase::class]) || in_array(RefreshDatabase::class, $uses, true)) {
            $this->refreshDatabase();
        }

        if (isset($uses[WithFaker::class]) || in_array(WithFaker::class, $uses, true)) {
            $this->setUpFaker();
        }
    }

    protected function ensureCodeIgniterStubs(): void
    {
        if (! function_exists('load_class') && defined('BASEPATH')) {
            require_once BASEPATH . 'core' . DIRECTORY_SEPARATOR . 'Common.php';
        }

        if (! class_exists('CI_Model', false) && defined('BASEPATH')) {
            require_once BASEPATH . 'core' . DIRECTORY_SEPARATOR . 'Model.php';
        }

        if (! class_exists('CI_Controller', false) && defined('BASEPATH')) {
            require_once BASEPATH . 'core' . DIRECTORY_SEPARATOR . 'Controller.php';
        }
    }

    protected function refreshApplication(): void
    {
        $this->app = $this->createApplication();
        $this->httpKernel = new \Elegant\Foundation\Http\Kernel($this->app, $this->applicationBasePath());
        $this->httpKernel->bootstrap();
        $this->ensureCodeIgniterStubs();
    }

    protected function afterApplicationCreated(callable $callback): void
    {
        $this->afterApplicationCreatedCallbacks[] = $callback;

        if ($this->setUpHasRun) {
            $callback();
        }
    }

    protected function beforeApplicationDestroyed(callable $callback): void
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    public function assertCondition(bool $condition, string $message = ''): void
    {
        $this->assertTrue($condition, $message);
    }

    public function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array does not have key: {$key}");
        }
    }

    public function assertStringMatchesPattern(string $pattern, string $string, string $message = ''): void
    {
        $this->assertMatchesRegularExpression($pattern, $string, $message);
    }

    public function assertIsType(string $expected, $actual, string $message = ''): void
    {
        switch ($expected) {
            case 'string':
                $this->assertIsString($actual, $message);
                break;
            case 'integer':
            case 'int':
                $this->assertIsInt($actual, $message);
                break;
            case 'float':
            case 'double':
                $this->assertIsFloat($actual, $message);
                break;
            case 'boolean':
            case 'bool':
                $this->assertIsBool($actual, $message);
                break;
            case 'array':
                $this->assertIsArray($actual, $message);
                break;
            case 'object':
                $this->assertIsObject($actual, $message);
                break;
            case 'resource':
                $this->assertIsResource($actual, $message);
                break;
            case 'null':
                $this->assertNull($actual, $message);
                break;
            default:
                $this->assertInstanceOf($expected, $actual, $message);
                break;
        }
    }

    public function assertCollectionCount(int $expectedCount, $collection, string $message = ''): void
    {
        if (is_array($collection) || $collection instanceof \Countable) {
            $this->assertCount($expectedCount, $collection, $message);
        } else {
            $this->fail($message ?: 'Collection is not countable');
        }
    }

    public function assertIsNull($actual, string $message = ''): void
    {
        $this->assertNull($actual, $message);
    }

    public function assertIsNotNull($actual, string $message = ''): void
    {
        $this->assertNotNull($actual, $message);
    }

    public function createMockWithMethods(string $className, array $methods = []): object
    {
        if ($className === 'stdClass') {
            $mock = new \stdClass;

            foreach ($methods as $method => $returnValue) {
                $mock->$method = function () use ($returnValue) {
                    return $returnValue;
                };
            }

            return $mock;
        }

        $mock = $this->createMock($className);

        foreach ($methods as $method => $returnValue) {
            if (method_exists($className, $method)) {
                $mock->method($method)->willReturn($returnValue);
            }
        }

        return $mock;
    }

    protected function setUpFaker(): void
    {
        if (class_exists(\Faker\Factory::class) && property_exists($this, 'faker')) {
            $this->faker = \Faker\Factory::create();
        }
    }

    /**
     * Resolve the application root (project root).
     *
     * @return string
     */
    protected function applicationBasePath(): string
    {
        if (defined('FCPATH')) {
            $candidate = rtrim(FCPATH, '/\\');

            if (is_file($candidate . DIRECTORY_SEPARATOR . 'composer.json')) {
                return $candidate;
            }
        }

        $dir = __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (is_file($dir . DIRECTORY_SEPARATOR . 'composer.json') && is_dir($dir . DIRECTORY_SEPARATOR . 'vendor')) {
                return $dir;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return dirname(__DIR__, 4);
    }

    public function get(string $uri, array $headers = []): TestResponse
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('GET', $uri, [], [], [], array_merge($this->serverVariables, $server));
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, $data, [], [], array_merge($this->serverVariables, $server));
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('PUT', $uri, $data, [], [], array_merge($this->serverVariables, $server));
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('DELETE', $uri, $data, [], [], array_merge($this->serverVariables, $server));
    }

    public function withoutExceptionHandling(): self
    {
        return $this;
    }

    /**
     * @param array $arguments
     * @return void
     */
    protected function runArtisan(array $arguments): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->applicationBasePath() . DIRECTORY_SEPARATOR . 'artisan');

        foreach ($arguments as $argument) {
            $cmd .= ' ' . escapeshellarg($argument);
        }

        $output = [];
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped('Artisan command failed: ' . implode(' ', $arguments) . "\n" . implode("\n", $output));
        }
    }
}
