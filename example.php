<?php
//require 'vendor/autoload.php';
//or 
require 'PlatformConfig.php';
$conf= PlatformConfig::config();
var_dump($conf["application"]->name);
var_dump($conf["relationships"]->first_db[0]->host);
var_dump($conf["relationships"]->first_db[0]->username);

