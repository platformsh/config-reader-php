<?php
namespace Platformsh\Config;
/**
* Reads Platform.sh configuration from environment and returns a single object
*/

class PlatformConfig
{
  protected static $configuration = array();
  private function __construct() {} // make this private so we can't instanciate
    public static function set($key, $val)
    {
      self::$configuration[$key] = $val;
    }
    public static function get($key)
    {
      return self::$configuration[$key];
    }
    public static function config()
    {
      return self::$configuration;
    }
    static function read_base64_json($var_name){
      try {
        return  json_decode(base64_decode(getenv($var_name)));
      }
      catch (Exception $e) {
        echo 'Exception : ',  $e->getMessage(), " $var_name does not seem to exist\n";
        return null;
      }
    }
  }
    
  if (getenv('PLATFORM_PROJECT')){
    try {
      PlatformConfig::set("application", PlatformConfig::read_base64_json('PLATFORM_APPLICATION')); 
      PlatformConfig::set("relationships", PlatformConfig::read_base64_json('PLATFORM_RELATIONSHIPS')); 
      PlatformConfig::set("variables", PlatformConfig::read_base64_json('PLATFORM_VARIABLES')); 
      PlatformConfig::set("app_dir", PlatformConfig::read_base64_json('PLATFORM_APP_DIR')); 
      PlatformConfig::set("environment", PlatformConfig::read_base64_json('PLATFORM_ENVIRONMENT')); 
      PlatformConfig::set("project", PlatformConfig::read_base64_json('PLATFORM_PROJECT')); 
        
      PlatformConfig::set("application_name", getenv('PLATFORM_APPLICATION_NAME'));
      PlatformConfig::set("app_dir", getenv('PLATFORM_APP_DIR'));
      PlatformConfig::set("environment", getenv('PLATFORM_ENVIRONMENT'));
      PlatformConfig::set("project", getenv('PLATFORM_PROJECT'));
      PlatformConfig::set("port", getenv('PORT'));
    }
    catch (Exception $e) {
      echo 'Exception : ',  $e->getMessage(), " could not decode Platform.sh environment\n";
    }
        
  } else {
    trigger_error("This does not seem to be running on Platform.sh", E_USER_NOTICE);
  }