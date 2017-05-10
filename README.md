# platformsh/config-reader
[![Build Status](https://travis-ci.org/platformsh/platformsh-config-reader-php.svg?branch=master)](https://travis-ci.org/platformsh/platformsh-config-reader-php)

A small helper to access a Platform.sh application's configuration, via
environment variables.

Include it in your project with:

```bash
composer require platformsh/config-reader
```

## Usage

```php
// Load the helper via Composer. Usually this is done once for your application.
require 'vendor/autoload.php';

$config = new \Platformsh\ConfigReader\Config();

// You can check for any particular value being available (recommended):
if (isset($config->relationships['database'][0])) {
    $database = $config->relationships['database'][0];

    // Now $database is an array representing a database service.
}

// Or you can check that any configuration is available at all:
if ($config->isAvailable()) {
    var_dump($config->project);
    var_dump($config->application_name);
    var_dump($config->relationships['database'][0]['host']);
}
```
