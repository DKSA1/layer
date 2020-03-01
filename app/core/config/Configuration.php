<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:44
 */
namespace layer\core\config;

use Exception;
use layer\core\http\HttpHeaders;

abstract class Configuration
{
    const ConfigurationFile = "configuration.json";
    private static $configuration;
    public static $environment;

    //Read the JSON config file
    static function load()
    {
        if(file_exists(PATH."app\core\config\\".Configuration::ConfigurationFile))
        {
            if($data = file_get_contents(PATH."app\core\config\\".Configuration::ConfigurationFile))
            {
                self::$configuration = json_decode($data,true);
                self::$environment = self::get("layer/environment");
            }
        }
    }

    //TODO : validation config file
    private static function checkConfigFile() {

    }

    //Retrieve data from config
    static function get($keys = "", $throwException = true)
    {
        $subArr = self::$configuration;
        if(self::$configuration != null)
        {
            $arrKey = explode("/",trim($keys,"/"));
            foreach ($arrKey as $k){
                if(array_key_exists($k,$subArr))
                {
                    $subArr = $subArr[$k];
                } else {
                    if ($throwException) {
                        throw new Exception("Une erreur de configuration est survenue ! ", HttpHeaders::InternalServerError);
                    } else {
                        return null;
                    }
                }
            }
        }
        return $subArr;
    }

}