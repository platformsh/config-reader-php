# Platform.sh Config Reader (PHP)

![Quality Assurance](https://github.com/platformsh/config-reader-php/workflows/Quality%20Assurance/badge.svg)

This library provides a streamlined and easy to use way to interact with a Platform.sh environment.  It offers utility methods to access routes and relationships more cleanly than reading the raw environment variables yourself.

This library requires PHP 7.2 or later.

## Install

```bash
composer require platformsh/config-reader
```

## Usage Example

Example:

```php
use Platformsh\ConfigReader\Config;

$config = new Config();

if (!$config->isValidPlatform()) {
    die("Not in a Platform.sh Environment.");
}

$credentials = $config->credentials('database');

$conn = new \PDO($config->formattedCredentials('database', 'pdo_mysql'), $credentials['username'], $credentials['password'], [
    // Always use Exception error mode with PDO, as it's more reliable.
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    // So we don't have to mess around with cursors and unbuffered queries by default.
    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
    // Make sure MySQL returns all matched rows on update queries including
    // rows that actually didn't have to be updated because the values didn't
    // change. This matches common behavior among other database systems.
    \PDO::MYSQL_ATTR_FOUND_ROWS => TRUE,
]);

// Do stuff with the $conn here.
```

## API Reference

### Create a config object

```php
use Platformsh\ConfigReader\Config;

$config = new Config();
```

`config` is now a `Platformsh\ConfigReder\Config` object that provides access to the Platform.sh environment.

The `isValidPlatform()` method returns `true` if the code is running in a context that has Platform.sh environment variables defined.  If it returns `false` then most other functions will throw exceptions if used.

### Inspect the environment

The following methods return `true` or `false` to help determine in what context the code is running:

```php
$config->inBuild();

$config->inRuntime();

$config->onDedicated();

$config->onProduction();
```

> **Note:**
>
> Platform.sh will no longer refer to its [99.99% uptime SLA product](https://platform.sh/solutions/) as "Enterprise", but rather as "Dedicated". Configuration Reader libraries have in turn been updated to include an `onDedicated` method to replace `onEnterprise`. For now `onEnterprise` remains available. It now calls the new method and no breaking changes have been introduced.
>
> It is recommended that you update your projects to use `onDedicated` as soon as possible, as `onEnterprise` will be removed in a future version of this library.

### Read environment variables

The following magic properties return the corresponding environment variable value.  See the [Platform.sh documentation](https://docs.platform.sh/development/variables.html) for a description of each.

The following are available both in Build and at Runtime:

```php
$config->applicationName;

$config->appDir;

$config->project;

$config->treeId;

$config->projectEntropy;
```

The following are available only if `inRuntime()` returned `true`:

```php
$config->branch;

$config->documentRoot;

$config->smtpHost;

$config->environment;

$config->socket;

$config->port;
```

### Reading service credentials

[Platform.sh services](https://docs.platform.sh/configuration/services.html) are defined in a `services.yaml` file, and exposed to an application by listing a `relationship` to that service in the application's `.platform.app.yaml` file.  User, password, host, etc. information is then exposed to the running application in the `PLATFORM_RELATIONSHIPS` environment variable, which is a base64-encoded JSON string.  The following method allows easier access to credential information than decoding the environment variable yourself.

```php
$creds = $config->credentials('database');
```

The return value of `credentials()` is an associative array matching the relationship JSON object, which includes the appropriate user, password, host, database name, and other pertinent information.  See the [Service documentation](https://docs.platform.sh/configuration/services.html) for your service for the exact structure and meaning of each property.  In most cases that information can be passed directly to whatever other client library is being used to connect to the service.

To make sure that a relationship is defined before you try to access credentials out of it, use the `hasRelationship()` method:

```php
if ($config->hasRelationship('database')) {
    $creds = $config->credentials('database');
    // ...
}
```

## Formatting service credentials

In some cases the library being used to connect to a service wants its credentials formatted in a specific way; it could be a DSN string of some sort or it needs certain values concatenated to the database name, etc.  For those cases you can use "Credential Formatters".  A Credential Formatter is any `callable` (function, anonymous function, object method, etc.) that takes a credentials array and returns any type, since the library may want different types.

Credential Formatters can be registered on the configuration object, and a few are included out of the box.  That allows 3rd party libraries to ship their own formatters that can be easily integrated into the `Config` object to allow easier use.

```php
function formatMyService(array $credentials) string
{
	return "some string based on $credentials";
}

// Call this in setup.
$config->registerFormatter("my_service", 'formatMyService');


// Then call this method to get the formatted version

$formatted = $config->FormattedCredentials("database", "my_service");
```

The first parameter is the name of a relationship defined in `.platform.app.yaml`.  The second is a formatter that was previously registered with `registerFormatter()`.  If either the service or formatter is missing an exception will be thrown.  The type of `formatted` will depend on the formatter function and can be safely passed directly to the client library.

Two formatters are included out of the box:

* `pdo_mysql` returns a DSN appropriate for using with `PDO` to connect to MySQL or MariaDB.  Note that `PDO` will still need the username and password from the credentials array passed as separate parameters.
* `pdo_pgsql` returns a DSN appropriate for using with `PDO` to connect to PostgreSQL.  Note that `PDO` will still need the username and password from the credentials array passed as separate parameters.

### Reading Platform.sh variables

Platform.sh allows you to define arbitrary variables that may be available at build time, runtime, or both.  They are stored in the `PLATFORM_VARIABLES` environment variable, which is a base64-encoded JSON string.

The following two methods allow access to those values from your code without having to bother decoding the values yourself:

```php
$config->variables();
```

This method returns an associative array of all variables defined.  Usually this method is not necessary and `$config->variable()` is preferred.

```php
$config->variable("foo", "default");
```

This method looks for the "foo" variable.  If found, it is returned.  If not, the optional second parameter is returned as a default.

### Reading Routes

[Routes](https://docs.platform.sh/configuration/routes.html) on Platform.sh define how a project will handle incoming requests; that primarily means what application container will serve the request, but it also includes cache configuration, TLS settings, etc.  Routes may also have an optional ID, which is the preferred way to access them.

```php
$config->getRoute("main");
```

The `getRoute()` method takes a single string for the route ID ("main" in this case) and returns the corresponding route array.  If the route is not found it will throw an exception.

To access all routes, or to search for a route that has no ID, the `routes()` method returns an associative array of routes keyed by their URL.  That mirrors the structure of the `PLATFORM_ROUTES` environment variable.

If called in the build phase an exception is thrown.
