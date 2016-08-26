<?php

namespace Platformsh\ConfigReader\Tests;

use Platformsh\ConfigReader\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    private $mockEnv;

    protected function setUp()
    {
        $this->mockEnv = [
          'PLATFORM_PROJECT'       => 'test-project',
          'PLATFORM_ENVIRONMENT'   => 'test-environment',
          'PLATFORM_APPLICATION'   => $this->encode(['type' => 'php:7.0']),
          'PLATFORM_RELATIONSHIPS' => $this->encode([
            'database' => [0 => ['host' => '127.0.0.1']],
          ]),
          'PLATFORM_NEW'           => 'some-new-variable',
        ];
    }

    public function testConfig()
    {
        $config = new Config();
        $this->assertFalse($config->isAvailable());

        $config = new Config($this->mockEnv);

        $this->assertMockEnv($config);

        $this->setExpectedException('Exception', 'not found');
        /** @noinspection PhpUndefinedFieldInspection */
        $config->nonexistent;
    }

    public function testInvalidJson()
    {
        $config = new Config([
          'PLATFORM_ENVIRONMENT' => 'test-environment',
          'PLATFORM_VARIABLES'   => base64_encode('{some-invalid-json}'),
        ]);

        $this->setExpectedException('Exception', 'Error decoding JSON');
        $config->variables;
    }

    public function testFallbackToServerVariable()
    {
        if (0 !== count($_ENV) || php_sapi_name() !== 'cli') {
            self::markTestSkipped('Environment variable is not empty. Are you running this test via web?!');
        }

        $_SERVER = array_merge($_SERVER, $this->mockEnv);

        $config = new Config();

        $this->assertMockEnv($config);
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

    /**
     * @param $config
     */
    protected function assertMockEnv(Config $config)
    {
        $this->assertTrue($config->isAvailable());
        $this->assertEquals('php:7.0', $config->application['type']);
        $this->assertEquals('test-project', $config->project);

        $this->assertTrue(isset($config->relationships));
        $this->assertTrue(isset($config->relationships['database'][0]));
        $this->assertEquals('127.0.0.1', $config->relationships['database'][0]['host']);

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertEquals('some-new-variable', $config->new);
    }
}
