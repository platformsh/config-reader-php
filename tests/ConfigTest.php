<?php

namespace Platformsh\ConfigReader;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConfig()
    {
        //$this->expectException(\Exception::class);
        //$this->expectExceptionMessage('Error decoding JSON');

        $config = new Config();
        $this->assertFalse($config->isAvailable());

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
