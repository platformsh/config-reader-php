<?php

namespace Platformsh\ConfigReader;

/**
 * @class Config
 * Reads Platform.sh configuration from environment variables.
 *
 * @link https://docs.platform.sh/development/variables.html
 *
 * The following are 'magic' properties that may exist on a Config object.
 * Before accessing a property, check its existence with
 * isset($config->variableName) or !empty($config->variableName). Attempting to
 * access a nonexistent variable will throw an exception.
 *
 * @property-read string $project
 *   The project ID.
 * @property-read string $environment
 *   The environment ID (usually the Git branch name).
 * @property-read string $application_name
 *   The name of the application, as defined in its configuration.
 * @property-read string $tree_id
 *   An ID identifying the application tree before it was built: a unique hash
 *   is generated based on the contents of the application's files in the
 *   repository.
 * @property-read string $app_dir
 *   The absolute path to the application.
 * @property-read string $document_root
 *   The absolute path to the web root of the application.
 * @property-read string $mode
 *   The hosting mode (this will only be set on Platform.sh Enterprise, and it
 *   will have the value 'enterprise').
 * @property-read array  $application
 *   The application's configuration, as defined in the .platform.app.yaml file.
 * @property-read array  $relationships
 *   The environment's relationships to other services. The keys are the name of
 *   the relationship (as configured for the application), and the values are
 *   arrays of relationship instances. For example, the hostname of a 'mysql'
 *   relationship may be stored in $config->relationships['mysql'][0]['host'].
 * @property-read array  $routes
 *   The routes configured for the environment.
 * @property-read array  $variables
 *   Custom environment variables.
 * @property-read string $smtp_host
 *   The hostname of the Platform.sh default SMTP server (an empty string if
 *   emails are disabled on the environment).
 */
class Config
{
    private $config = [];
    private $environmentVariables = [];
    private $envPrefix = '';

    /**
     * Checks whether any configuration is available.
     *
     * @return bool
     *   True if configuration can be used, false otherwise.
     */
    public function isAvailable()
    {
        return isset($this->environmentVariables[$this->envPrefix . 'ENVIRONMENT']);
    }

    /**
     * Constructs a ConfigReader object.
     *
     * @param array|null  $environmentVariables
     *   The environment variables to read. Defaults to the current environment.
     * @param string|null $envPrefix
     *   The prefix for environment variables. Defaults to 'PLATFORM_'.
     */
    public function __construct(array $environmentVariables = null, $envPrefix = null)
    {
        $this->environmentVariables = $environmentVariables === null ? $this->getEnv() : $environmentVariables;
        $this->envPrefix = $envPrefix === null ? 'PLATFORM_' : $envPrefix;
    }

    /**
     * Load environment variables.
     *
     * @return array
     */
    private function getEnv()
    {
        return PHP_VERSION_ID >= 70100 ? getenv() : $_ENV;
    }

    /**
     * Decodes a Platform.sh environment variable.
     *
     * @param string $variable
     *   Base64-encoded JSON (the content of an environment variable).
     *
     * @throws \Exception if there is a JSON decoding error.
     *
     * @return mixed
     *   An associative array (if representing a JSON object), or a scalar type.
     */
    private function decode($variable)
    {
        $result = json_decode(base64_decode($variable), true);
        if (json_last_error()) {
            throw new \Exception(
                sprintf('Error decoding JSON, code: %d', json_last_error())
            );
        }

        return $result;
    }

    /**
     * Determines whether a variable needs to be decoded.
     *
     * @param string $property
     *   The property name.
     *
     * @return bool
     *   True if the variable is base64- and JSON-encoded, false otherwise.
     */
    private function shouldDecode($property)
    {
        return in_array(strtolower($property), [
            'application',
            'relationships',
            'routes',
            'variables',
        ]
        );
    }

    /**
     * Get the name of an environment variable.
     *
     * @param string $property
     *   The property name, e.g. 'relationships'.
     *
     * @return string
     *   The environment variable name, e.g. PLATFORM_RELATIONSHIPS.
     */
    private function getVariableName($property)
    {
        return $this->envPrefix . strtoupper($property);
    }

    /**
     * Gets a configuration property.
     *
     * @param string $property
     *   A (magic) property name. The properties are documented in the DocBlock
     *   for this class.
     *
     * @throws \Exception if a variable is not found, or if decoding fails.
     *
     * @return mixed
     *   The return types are documented in the DocBlock for this class.
     */
    public function __get($property)
    {
        $variableName = $this->getVariableName($property);
        if (!isset($this->config[$variableName])) {
            if (!array_key_exists($variableName, $this->environmentVariables)) {
                throw new \Exception(sprintf('Environment variable not found: %s', $variableName));
            }
            $value = $this->environmentVariables[$variableName];
            if ($this->shouldDecode($property)) {
                $value = $this->decode($value);
            }
            $this->config[$variableName] = $value;
        }

        return $this->config[$variableName];
    }

    /**
     * Checks whether a configuration property is set.
     *
     * @param string $property
     *   A (magic) property name.
     *
     * @return bool
     *   True if the property exists and is not null, false otherwise.
     */
    public function __isset($property)
    {
        return isset($this->environmentVariables[$this->getVariableName($property)]);
    }
}
