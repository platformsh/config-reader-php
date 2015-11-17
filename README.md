# Config

A small helper to access a Platform.sh application's configuration, via
environment variables.

```php
// Load the helper via Composer. Usually this is done once for your application.
require 'vendor/autoload.php';

use Platformsh\Config;

$config = new Config();

// You can check for any particular value being available:
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
