<?php

namespace Platformsh\ConfigReader;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{

    /**
     * A mock environment to simulate build time.
     *
     * @var array
     */
    protected $mockEnvironmentBuild = [];

    /**
     * A mock environment to simulate runtime.
     *
     * @var array
     */
    protected $mockEnvironmentDeploy = [];

    public function setUp()
    {
        $env = $this->loadJsonFile('ENV');

        // These sub-values are always encoded.
        foreach (['PLATFORM_APPLICATION', 'PLATFORM_VARIABLES'] as $item) {
            $env[$item] = $this->encode($this->loadJsonFile($item));
        }

        $this->mockEnvironmentBuild = $env;

        // These sub-values are always encoded.
        foreach (['PLATFORM_ROUTES', 'PLATFORM_RELATIONSHIPS'] as $item) {
            $env[$item] = $this->encode($this->loadJsonFile($item));
        }

        $envRuntime = $this->loadJsonFile('ENV_runtime');
        $env = array_merge($env, $envRuntime);

        $this->mockEnvironmentDeploy = $env;

    }

    protected function loadJsonFile(string $name) : array
    {
        return json_decode(file_get_contents("tests/valid/{$name}.json"), true);
    }

    public function test_not_on_platform_returns_correctly() : void
    {
        $config = new Config();

        $this->assertFalse($config->isAvailable());
    }

    public function test_on_platform_returns_correctly() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $this->assertTrue($config->isAvailable());
    }

    public function testConfig()
    {
        //$this->expectException(\Exception::class);
        //$this->expectExceptionMessage('Error decoding JSON');

        $mockEnv = [
            'PLATFORM_PROJECT' => 'test-project',
            'PLATFORM_ENVIRONMENT' => 'test-environment',
            'PLATFORM_APPLICATION' => $this->encode(['type' => 'php:7.0']),
            'PLATFORM_RELATIONSHIPS' => $this->encode([
                'database' => [0 => ['host' => '127.0.0.1']],
            ]),
            'PLATFORM_NEW' => 'some-new-variable',
        ];

        $config = new Config($mockEnv);

        $this->assertTrue($config->isAvailable());
        $this->assertEquals('php:7.0', $config->application['type']);
        $this->assertEquals('test-project', $config->project);

        $this->assertTrue(isset($config->relationships));
        $this->assertTrue(isset($config->relationships['database'][0]));
        $this->assertEquals('127.0.0.1', $config->relationships['database'][0]['host']);

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertEquals('some-new-variable', $config->new);

    }

    public function testInvalidJson()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error decoding JSON, code: 4');

        $config = new Config([
            'PLATFORM_ENVIRONMENT' => 'test-environment',
            'PLATFORM_VARIABLES' => base64_encode('{some-invalid-json}'),
        ]);

        $config->variables;
    }

    public function testCustomPrefix()
    {
        $config = new Config(['ENVIRONMENT' => 'test-environment'], '');
        $this->assertTrue($config->isAvailable());
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function encode($value)
    {
        return base64_encode(json_encode($value));
    }
}
