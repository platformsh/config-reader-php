<?php
declare(strict_types=1);

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
 * // These properties are available at build time and run time.
 *
 * @property-read string $project
 *   The project ID.
 * @property-read string $applicationName
 *   The name of the application, as defined in its configuration.
 * @property-read string $treeId
 *   An ID identifying the application tree before it was built: a unique hash
 *   is generated based on the contents of the application's files in the
 *   repository.
 * @property-read string $appDir
 *   The absolute path to the application.
 * @property-read string $projectEntropy
 *   A random string generated for each project, useful for generating hash keys.
 *
 * // These properties are only available at runtime.
 *
 * @property-read string $branch
 *   The Git branch name.
 * @property-read string $environment
 *   The environment ID (usually the Git branch plus a hash).
 * @property-read string $documentRoot
 *   The absolute path to the web root of the application.
 * @property-read string $smtpHost
 *   The hostname of the Platform.sh default SMTP server (an empty string if
 *   emails are disabled on the environment).
 * @property-read string $port
 *   The TCP port number the application should listen to for incoming requests.
 * @property-read string $socket
 *   The Unix socket the application should listen to for incoming requests.
 *
 */
class Config
{

    /**
     * Local index of the variables that can be accessed as direct properties (build and runtime).
     *
     * The key is the property that will be read.  The value is the environment variable, minus
     * prefix, that contains the value to look up.
     *
     * @var array
     */
    protected $directVariables = [
        'project' => 'PROJECT',
        'appDir' => 'APP_DIR',
        'applicationName' => 'APPLICATION_NAME',
        'treeId' => 'TREE_ID',
        'projectEntropy' => 'PROJECT_ENTROPY',
    ];

    /**
     * Local index of the variables that can be accessed as direct properties (runtime only).
     *
     * The key is the property that will be read.  The value is the environment variable, minus
     * prefix, that contains the value to look up.
     *
     * @var array
     */
    protected $directVariablesRuntime = [
        'branch' => 'BRANCH',
        'environment' => 'ENVIRONMENT',
        'documentRoot' => 'DOCUMENT_ROOT',
        'smtpHost' => 'SMTP_HOST',
    ];

    protected $unPrefixedVariablesRuntime = [
        'port' => 'PORT',
        'socket' => 'SOCKET',
    ];

    /**
     * A local copy of all environment variables as of when the object was initialized.
     * @var array
     */
    protected $environmentVariables = [];

    /**
     * The vendor prefix for all environment variables we care about.
     *
     * @var string
     */
    protected $envPrefix = '';

    /**
     * The routes definition array.
     *
     * Only available at runtime.
     *
     * @var array
     */
    protected $routesDef = [];

    /**
     * The relationships definition array.
     *
     * Only available at runtime.
     *
     * @var array
     */
    protected $relationshipsDef = [];

    /**
     * The variables definition array.
     *
     * Available in both build and runtime, although possibly with different values.
     *
     * @var array
     */
    protected $variablesDef = [];

    /**
     * The application definition array.
     *
     * This is, approximately, the .platform.app.yaml file in nested array form.
     *
     * @var array
     */
    protected $applicationDef = [];

    /**
     * A map of formatter name strings to callable formatters.
     *
     * @var array
     */
    protected $credentialFormatters = [];

    /**
     * Constructs a Config object.
     *
     * @param array|null  $environmentVariables
     *   The environment variables to read. Defaults to the current environment.
     * @param string $envPrefix
     *   The prefix for environment variables. Defaults to 'PLATFORM_'.
     */
    public function __construct(array $environmentVariables = null, string $envPrefix = 'PLATFORM_')
    {
        $this->environmentVariables = $environmentVariables ?? getenv();
        $this->envPrefix = $envPrefix;

        if ($this->isValidPlatform()) {
            if ($this->inRuntime()) {
                if ($routes = $this->getValue('ROUTES')) {
                    $this->routesDef = $this->decode($routes);
                }
                if ($relationships = $this->getValue('RELATIONSHIPS')) {
                    $this->relationshipsDef = $this->decode($relationships);
                }

                $this->registerFormatter('pdo_mysql', [$this, 'pdoMySQLFormatter']);
                $this->registerFormatter('pdo_pgsql', [$this, 'pdoPostgreSQLFormatter']);
            }
            if ($variables = $this->getValue('VARIABLES')) {
                $this->variablesDef = $this->decode($variables);
            }
            if ($application = $this->getValue('APPLICATION')) {
                $this->applicationDef = $this->decode($application);
            }
        }
    }

    /**
     * Checks whether the code is running on a platform with valid environment variables.
     *
     * @return bool
     *   True if configuration can be used, false otherwise.
     */
    public function isValidPlatform() : bool
    {
        return (bool)$this->getValue('APPLICATION_NAME');
    }

    /**
     * Checks whether the code is running in a build environment.
     *
     * If false, it's running at deploy time.
     *
     * @return bool
     */
    public function inBuild() : bool
    {
        return $this->isValidPlatform() && !$this->getValue('ENVIRONMENT');
    }

    /**
     * Checks whether the code is running in a runtime environment.
     *
     * @return bool
     */
    public function inRuntime() : bool
    {
        return $this->isValidPlatform() && $this->getValue('ENVIRONMENT');
    }

    /**
     * Retrieves the credentials for accessing a relationship.
     *
     * The relationship must be defined in the .platform.app.yaml file.
     *
     * @param string $relationship
     *   The relationship name as defined in .platform.app.yaml.
     * @param int $index
     *   The index within the relationship to access.  This is always 0, but reserved
     *   for future extension.
     * @return array
     *   The credentials array for the service pointed to by the relationship.
     * @throws BuildTimeVariableAccessException
     *   Thrown if called in a in the build phase, where relationships are not defined.
     * @throws \InvalidArgumentException
     *   If the relationship/index pair requested does not exist.
     */
    public function credentials(string $relationship, int $index = 0) : array
    {
        if (!$this->isValidPlatform()) {
            throw new NotValidPlatformException('You are not running on Platform.sh, so relationships are not available.');
        }

        if ($this->inBuild()) {
            throw new BuildTimeVariableAccessException('Relationships are not available during the build phase.');
        }

        if (empty($this->relationshipsDef[$relationship])) {
            throw new \InvalidArgumentException(sprintf('No relationship defined: %s.  Check your .platform.app.yaml file.', $relationship));
        }
        if (empty($this->relationshipsDef[$relationship][$index])) {
            throw new \InvalidArgumentException(sprintf('No index %d defined for relationship: %s.  Check your .platform.app.yaml file.', $index, $relationship));
        }

        return $this->relationshipsDef[$relationship][$index];
    }

    /**
     * Returns a variable from the VARIABLES array.
     *
     * Note: variables prefixed with `env:` can be accessed as normal environment variables.
     * This method will return such a variable by the name with the prefix still included.
     * Generally it's better to access those variables directly.
     *
     * @param string $name
     *   The name of the variable to retrieve.
     * @param mixed $default
     *   The default value to return if the variable is not defined. Defaults to null.
     * @return mixed
     *   The value of the variable, or the specified default.  This may be a string or an array.
     */
    public function variable(string $name, $default = null)
    {
        if (!$this->isValidPlatform()) {
            return $default;
        }

        return $this->variablesDef[$name] ?? $default;
    }

    /**
     * Returns the full variables array.
     *
     * If you're looking for a specific variable, the variable() method is a more robust option.
     * This method is for cases where you want to scan the whole variables list looking for a pattern.
     *
     * @return array
     *   The full variables array.
     */
    public function variables() : array
    {
        if (!$this->isValidPlatform()) {
            throw new NotValidPlatformException('You are not running on Platform.sh, so the variables array is not available.');
        }

        return $this->variablesDef;
    }

    /**
     * Returns the routes definition.
     *
     * @return array
     *   The routes array, in PHP nested array form.
     * @throws BuildTimeVariableAccessException
     *   If the routes are not accessible due to being in the wrong environment.
     */
    public function routes() : array
    {
        if (!$this->isValidPlatform()) {
            throw new NotValidPlatformException('You are not running on Platform.sh, so routes are not available.');
        }

        if ($this->inBuild()) {
            throw new BuildTimeVariableAccessException('Routes are not available during the build phase.');
        }

        return $this->routesDef;
    }

    /**
     * Returns a single route definition.
     *
     * Note: If no route ID was specified in routes.yaml then it will not be possible
     * to look up a route by ID.
     *
     * @param string $id
     *   The ID of the route to load.
     * @return array
     *   The route definition.  The generated URL of the route is added as a "url" key.
     * @throws \InvalidArgumentException
     *   If there is no route by that ID, an exception is thrown.
     */
    public function getRoute(string $id) : array
    {
        foreach ($this->routes() as $url => $route) {
            if ($route['id'] == $id) {
                // This is non-standard, but means we don't need to return the array key in parallel.
                $route['url'] = $url;
                return $route;
            }
        }

        throw new \InvalidArgumentException(sprintf('No such route id found: %s', $id));
    }

    /**
     * Returns the application definition array.
     *
     * This is, approximately, the .platform.app.yaml file as a nested array.  However, it also
     * has other information added by Platform.sh as part of the build and deploy process.
     *
     * @return array
     *   The application definition array.
     */
    public function application() : array
    {
        if (!$this->isValidPlatform()) {
            throw new NotValidPlatformException('You are not running on Platform.sh, so the application definition is not available.');
        }

        return $this->applicationDef;
    }

    /**
     * Determines if the current environment is a Platform.sh Enterprise environment.
     *
     * @return bool
     *   True on an Enterprise environment, False otherwise.
     */
    public function onEnterprise() : bool
    {
        return $this->isValidPlatform() && $this->getValue('MODE') == 'enterprise';
    }

    /**
     * Determines if the current environment is a production environment.
     *
     * Note: There may be a few edge cases where this is not entirely correct on Enterprise,
     * if the production branch is not named `production`.  In that case you'll need to use
     * your own logic.
     *
     * @return bool
     *   True if the environment is a production environment, false otherwise.
     *   It will also return false if not running on Platform.sh or in the build phase.
     */
    public function onProduction() : bool
    {
        if (!$this->inRuntime()) {
            return false;
        }

        $prodBranch = $this->onEnterprise() ? 'production' : 'master';

        return $this->getValue('BRANCH') == $prodBranch;
    }

    /**
     * Adds a credential formatter to the configuration.
     *
     * A credential formatter is responsible for formatting the credentials for a relationship
     * in a way expected by a particular client library.  For instance, it can take the credentials
     * from Platform.sh for a PostgreSQL database and format them into a URL string expected by
     * PDO.  Use the formattedCredentials() method to get the formatted version of a particular
     * relationship.
     *
     * @param string name
     *   The name of the formatter.  This may be any arbitrary alphanumeric string.
     * @param {registerFormatterCallback} formatter
     *   A callback function that will format relationship credentials for a specific client library.
     * @return Config
     *   The called object, for chaining.
     */
    public function registerFormatter(string $name, callable $formatter) : self
    {
        $this->credentialFormatters[$name] = $formatter;
        return $this;
    }

    /**
     * Returns credentials for the specified relationship as formatted by the specified formatter.
     *
     * @param string relationship
     *   The relationship whose credentials should be formatted.
     * @param string formatter
     *   The registered formatter to use.  This must match a formatter previously registered
     *   with registerFormatter().
     * @return mixed
     *   The credentials formatted with the given formatter.
     * @throws NoCredentialFormatterFoundException
     */
    public function formattedCredentials(string $relationship, string $formatter)
    {
        if (empty($this->credentialFormatters[$formatter])) {
            throw new NoCredentialFormatterFoundException(sprintf('There is no credential formatter named "%s" registered. Did you remember to call registerFormatter()?', $formatter));
        }

        return $this->credentialFormatters[$formatter]($this->credentials($relationship));
    }

    /**
     * Determines if a relationship is defined, and thus has credentials available.
     *
     * @param string $relationship
     *   The name of the relationship to check.
     * @return bool
     *   True if the relationship is defined, false otherwise.
     */
    public function hasRelationship(string $relationship) : bool
    {
        return isset($this->relationshipsDef[$relationship]);
    }

    /**
     * Reads an environment variable, taking the prefix into account.
     *
     * @param string $name
     *   The variable to read.
     * @return string|null
     */
    protected function getValue(string $name) : ?string
    {
        $checkName = $this->envPrefix . strtoupper($name);
        return $this->environmentVariables[$checkName] ?? null;
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
    protected function decode($variable)
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
        if (!$this->isValidPlatform()) {
            throw new NotValidPlatformException(sprintf('You are not running on Platform.sh, so the %s variable are not available.', $property));
        }

        $isBuildVar = in_array($property, array_keys($this->directVariables));
        $isRuntimeVar = in_array($property, array_keys($this->directVariablesRuntime));
        // For now, all unprefixed variables are also runtime variables.  If that ever changes this
        // logic will change with it.
        $isUnprefixedVar = in_array($property, array_keys($this->unPrefixedVariablesRuntime));

        if ($this->inBuild() && $isRuntimeVar) {
            throw new BuildTimeVariableAccessException(sprintf('The %s variable is not available during build time.', $property));
        }

        if ($isBuildVar) {
            return $this->getValue($this->directVariables[$property]);
        }
        if ($isUnprefixedVar) {
            return $this->environmentVariables[$this->unPrefixedVariablesRuntime[$property]] ?? null;
        }
        if ($isRuntimeVar) {
            return $this->getValue($this->directVariablesRuntime[$property]);
        }

        throw new \InvalidArgumentException(sprintf('No such variable defined: %s', $property));
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
        if (!$this->isValidPlatform()) {
            return false;
        }

        $isBuildVar = in_array($property, array_keys($this->directVariables));
        $isRuntimeVar = in_array($property, array_keys($this->directVariablesRuntime));
        // For now, all unprefixed variables are also runtime variables.  If that ever changes this
        // logic will change with it.
        $isUnprefixedVar = in_array($property, array_keys($this->unPrefixedVariablesRuntime));

        if ($this->inBuild()) {
            return $isBuildVar && !is_null($this->$property);
        }

        if ($isBuildVar || $isRuntimeVar || $isUnprefixedVar) {
            return !is_null($this->$property);
        }

        return false;
    }

    /**
     * Returns a DSN for a PDO-MySQL connection.
     *
     * Note that the username and password will still be needed separately in the PDO constructor.
     *
     * @param array $credentials
     *   The credentials array from the relationships.
     * @return string
     *   A formatted PDO DSN.
     */
    protected function pdoMySqlFormatter(array $credentials) : string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s', $credentials['host'], $credentials['port'], $credentials['path']);
    }

    /**
     * Returns a DSN for a PDO-PostgreSQL connection.
     *
     * Note that the username and password will still be needed separately in the PDO constructor.
     *
     * @param array $credentials
     *   The credentials array from the relationships.
     * @return string
     *   A formatted PDO DSN.
     */
    protected function pdoPostgreSqlFormatter(array $credentials) : string
    {
        return sprintf('pgsql:host=%s;port=%d;dbname=%s', $credentials['host'], $credentials['port'], $credentials['path']);
    }

}
