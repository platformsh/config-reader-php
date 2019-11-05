<?php
declare(strict_types=1);

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

        $this->assertFalse($config->isValidPlatform());
    }

    public function test_on_platform_returns_correctly_in_runtime() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $this->assertTrue($config->isValidPlatform());
    }

    public function test_on_platform_returns_correctly_in_build() : void
    {
        $config = new Config($this->mockEnvironmentBuild);

        $this->assertTrue($config->isValidPlatform());
    }

    public function test_inbuild_in_build_phase_is_true() : void
    {
        $config = new Config($this->mockEnvironmentBuild);

        $this->assertTrue($config->inBuild());
    }

    public function test_inbuild_in_deploy_phase_is_false() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $this->assertFalse($config->inBuild());
    }

    public function test_inruntime_in_runtime_is_true() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $this->assertTrue($config->inRuntime());
    }

    public function test_inruntime_in_build_phase_is_false() : void
    {
        $config = new Config($this->mockEnvironmentBuild);

        $this->assertFalse($config->inRuntime());
    }

    public function test_load_routes_in_runtime_works() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $routes = $config->routes();

        $this->assertTrue(is_array($routes));
    }

    public function test_load_routes_in_build_fails() : void
    {
        $this->expectException(BuildTimeVariableAccessException::class);

        $config = new Config($this->mockEnvironmentBuild);
        $routes = $config->routes();
    }

    public function test_get_route_by_id_works() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $route = $config->getRoute('main');

        $this->assertEquals('https://www.{default}/', $route['original_url']);
    }

    public function test_get_non_existent_route_throws_exception() : void
    {
        $this->expectException(\InvalidArgumentException::class);

        $config = new Config($this->mockEnvironmentDeploy);

        $route = $config->getRoute('missing');
    }

    public function test_primary_route_returns_correct_route() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $route = $config->getPrimaryRoute();

        $this->assertEquals('https://www.{default}/', $route['original_url']);
        $this->assertEquals('main', $route['id']);
        $this->assertTrue($route['primary']);
    }

    public function test_upstream_routes() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $routes = $config->getUpstreamRoutes();

        $this->assertCount(3, $routes);
        $this->assertArrayHasKey('https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/', $routes);
        $this->assertEquals('https://www.{default}/', $routes['https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/']['original_url']);
    }

    public function test_upstream_routes_for_app() : void
    {
        $config = new Config($this->mockEnvironmentDeploy);

        $routes = $config->getUpstreamRoutes('app');

        $this->assertCount(2, $routes);
        $this->assertArrayHasKey('https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/', $routes);
        $this->assertEquals('https://www.{default}/', $routes['https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/']['original_url']);
    }

    public function test_upstream_routes_for_app_on_dedicated() : void
    {
        $env = $this->mockEnvironmentDeploy;
        // Simulate a Dedicated-style upstream name.
        $routeData = $this->loadJsonFile('PLATFORM_ROUTES');
        $routeData['https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/']['upstream'] = 'app:http';
        $env['PLATFORM_ROUTES'] = $this->encode($routeData);

        $config = new Config($env);

        $routes = $config->getUpstreamRoutes('app');

        $this->assertCount(2, $routes);
        $this->assertArrayHasKey('https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/', $routes);
        $this->assertEquals('https://www.{default}/', $routes['https://www.master-7rqtwti-gcpjkefjk4wc2.us-2.platformsh.site/']['original_url']);
    }

    public function test_ondedicated_returns_true_on_dedicated() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $env['PLATFORM_MODE'] = 'enterprise';
        $config = new Config($env);

        $this->assertTrue($config->onDedicated());
    }

    public function test_ondedicated_returns_false_on_standard() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertFalse($config->onDedicated());
    }

    public function test_onproduction_on_dedicated_prod_is_true() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $env['PLATFORM_MODE'] = 'enterprise';
        $env['PLATFORM_BRANCH'] = 'production';
        $config = new Config($env);

        $this->assertTrue($config->onProduction());
    }

    public function test_onproduction_on_dedicated_stg_is_false() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $env['PLATFORM_MODE'] = 'enterprise';
        $env['PLATFORM_BRANCH'] = 'staging';
        $config = new Config($env);

        $this->assertFalse($config->onProduction());

    }

    public function test_onproduction_on_standard_prod_is_true() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $env['PLATFORM_BRANCH'] = 'master';
        $config = new Config($env);

        $this->assertTrue($config->onProduction());
    }

    public function test_onproduction_on_standard_stg_is_false() : void
    {
        // The fixture has a non-master branch set by default.
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertFalse($config->onProduction());
    }

    public function test_credentials_existing_relationship_returns() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $creds = $config->credentials('database');

        $this->assertEquals('mysql', $creds['scheme']);
        $this->assertEquals('mysql:10.2', $creds['type']);
    }

    public function test_credentials_missing_relationship_throws() : void
    {
        $this->expectException(\InvalidArgumentException::class);

        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $creds = $config->credentials('does-not-exist');
    }

    public function test_credentials_missing_relationship_index_throws() : void
    {
        $this->expectException(\InvalidArgumentException::class);

        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $creds = $config->credentials('database', 3);
    }

    public function test_credentials_works_in_local() : void
    {
        $env = $this->mockEnvironmentDeploy;
        unset($env['PLATFORM_APPLICATION'], $env['PLATFORM_ENVIRONMENT'], $env['PLATFORM_BRANCH']);
        $config = new Config($env);

        $creds = $config->credentials('database');

        $this->assertEquals('mysql', $creds['scheme']);
        $this->assertEquals('mysql:10.2', $creds['type']);
    }

    public function test_hasRelationship_returns_true_for_existing_relationship() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertTrue($config->hasRelationship('database'));
    }

    public function test_hasRelationship_returns_false_for_missingrelationship() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertFalse($config->hasRelationship('missing'));
    }

    public function test_reading_existing_variable_works() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertEquals('someval', $config->variable('somevar'));
    }

    public function test_reading_missing_variable_returns_default() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertEquals('default-val', $config->variable('missing', 'default-val'));
    }

    public function test_variables_returns_on_platform() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $vars = $config->variables();

        $this->assertEquals('someval', $vars['somevar']);
    }

    public function test_build_property_in_build_exists() : void
    {
        $env = $this->mockEnvironmentBuild;
        $config = new Config($env);

        $this->assertEquals('/app', $config->appDir);
        $this->assertEquals('app', $config->applicationName);
        $this->assertEquals('test-project', $config->project);
        $this->assertEquals('abc123', $config->treeId);
        $this->assertEquals('def789', $config->projectEntropy);

        $this->assertTrue(isset($config->appDir));
        $this->assertTrue(isset($config->applicationName));
        $this->assertTrue(isset($config->project));
        $this->assertTrue(isset($config->treeId));
        $this->assertTrue(isset($config->projectEntropy));
    }

    public function test_build_and_deploy_properties_in_deploy_exists() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertEquals('/app', $config->appDir);
        $this->assertEquals('app', $config->applicationName);
        $this->assertEquals('test-project', $config->project);
        $this->assertEquals('abc123', $config->treeId);
        $this->assertEquals('def789', $config->projectEntropy);

        $this->assertEquals('feature-x', $config->branch);
        $this->assertEquals('feature-x-hgi456', $config->environment);
        $this->assertEquals('/app/web', $config->documentRoot);
        $this->assertEquals('1.2.3.4', $config->smtpHost);
        $this->assertEquals('8080', $config->port);
        $this->assertEquals('unix://tmp/blah.sock', $config->socket);

        $this->assertTrue(isset($config->appDir));
        $this->assertTrue(isset($config->applicationName));
        $this->assertTrue(isset($config->project));
        $this->assertTrue(isset($config->treeId));
        $this->assertTrue(isset($config->projectEntropy));

        $this->assertTrue(isset($config->branch));
        $this->assertTrue(isset($config->environment));
        $this->assertTrue(isset($config->documentRoot));
        $this->assertTrue(isset($config->smtpHost));
        $this->assertTrue(isset($config->port));
        $this->assertTrue(isset($config->socket));
    }

    public function test_build_and_deploy_properties_mocked_in_local_exists() : void
    {
        $env = $this->mockEnvironmentDeploy;
        unset($env['PLATFORM_APPLICATION'], $env['PLATFORM_ENVIRONMENT'], $env['PLATFORM_BRANCH']);
        $config = new Config($env);

        $this->assertEquals('/app', $config->appDir);
        $this->assertEquals('app', $config->applicationName);
        $this->assertEquals('test-project', $config->project);
        $this->assertEquals('abc123', $config->treeId);
        $this->assertEquals('def789', $config->projectEntropy);

        $this->assertEquals('/app/web', $config->documentRoot);
        $this->assertEquals('1.2.3.4', $config->smtpHost);
        $this->assertEquals('8080', $config->port);
        $this->assertEquals('unix://tmp/blah.sock', $config->socket);

        $this->assertTrue(isset($config->appDir));
        $this->assertTrue(isset($config->applicationName));
        $this->assertTrue(isset($config->project));
        $this->assertTrue(isset($config->treeId));
        $this->assertTrue(isset($config->projectEntropy));

        $this->assertTrue(isset($config->documentRoot));
        $this->assertTrue(isset($config->smtpHost));
        $this->assertTrue(isset($config->port));
        $this->assertTrue(isset($config->socket));
    }

    public function test_deploy_property_in_build_throws() : void
    {
        $this->expectException(BuildTimeVariableAccessException::class);

        $env = $this->mockEnvironmentBuild;
        $config = new Config($env);

        $this->assertFalse(isset($config->branch));

        $branch = $config->branch;
    }

    public function test_missing_property_throws_in_build() : void
    {
        $this->expectException(\InvalidArgumentException::class);

        $env = $this->mockEnvironmentBuild;
        $config = new Config($env);

        $this->assertFalse(isset($config->missing));

        $branch = $config->missing;
    }

    public function test_missing_property_throws_in_deploy() : void
    {
        $this->expectException(\InvalidArgumentException::class);

        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $this->assertFalse(isset($config->missing));

        $branch = $config->missing;
    }

    public function test_application_array_available() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $app = $config->application();

        $this->assertEquals('php:7.2', $app['type']);
    }

    public function test_invalid_json_throws() : void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error decoding JSON, code: 4');

        $config = new Config([
            'PLATFORM_APPLICATION_NAME' => 'app',
            'PLATFORM_ENVIRONMENT' => 'test-environment',
            'PLATFORM_VARIABLES' => base64_encode('{some-invalid-json}'),
        ]);
    }

    public function test_custom_prefix_works() : void
    {
        $config = new Config(['FAKE_APPLICATION_NAME' => 'test-application'], 'FAKE_');
        $this->assertTrue($config->isValidPlatform());
    }

    public function test_formattedCredentials_throws_when_no_formatter_defined() : void
    {
        $this->expectException(NoCredentialFormatterFoundException::class);

        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $config->formattedCredentials('database', 'not-defined');
    }

    public function test_formattedCredentials_calls_a_formatter() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $config->registerFormatter('test', function(array $credentials) {
            return 'called';
        });

        $formatted = $config->formattedCredentials('database', 'test');

        $this->assertEquals('called', $formatted);
    }

    public function test_pdomysql_formatter() : void
    {
        $env = $this->mockEnvironmentDeploy;
        $config = new Config($env);

        $formatted = $config->formattedCredentials('database', 'pdo_mysql');

        $this->assertEquals('mysql:host=database.internal;port=3306;dbname=main', $formatted);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function encode($value) : string
    {
        return base64_encode(json_encode($value));
    }
}
