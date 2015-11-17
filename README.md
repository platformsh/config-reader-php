usage:
```
<?php
require_once "PlatformConfig.php";
$conf= PlatformConfig::config();
var_dump($conf["relationships"]->first_db[0]->username);
var_dump($conf["name"];
```